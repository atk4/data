<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Exception;
use Atk4\Data\Persistence\Sql\Expression;

/**
 * @template TKey of object|array<mixed>
 * @template TValue of object|array<mixed>
 * @template TOwner of object
 */
class WeakAnalysingMap
{
    use WarnDynamicPropertyTrait;

    /** @var WeakAnalysingBoxedArray<array{}> workaround https://github.com/php/php-src/issues/13612, remove once PHP 8.3 support is dropped */
    private WeakAnalysingBoxedArray $destructedEarly;

    private int $indexCounter = -1;

    /** @var array<int, array<int, \WeakReference<(TKey is object ? TKey : WeakAnalysingBoxedArray<TKey>)>>> */
    private array $keyByIndexByHash = [];
    /** @var array<int, array{\WeakReference<(TValue is object ? TValue : WeakAnalysingBoxedArray<TValue>)>, int}> */
    private array $valueWithOwnerCountByIndex = [];
    /** @var \WeakMap<TOwner, object> */
    private \WeakMap $ownerDestructorHandlers;

    public function __construct()
    {
        $this->ownerDestructorHandlers = new \WeakMap();

        $this->destructedEarly = new WeakAnalysingBoxedArray([]);
    }

    final protected function makeHashFromKeyUpdate(\HashContext $hashContext, string $value): void
    {
        if (str_contains($value, "\xff")) {
            $value = str_replace("\xff", "\xff\xff", $value);
        }
        hash_update($hashContext, $value);
        hash_update($hashContext, "-\xff");
    }

    /**
     * @param ($hashContext is null ? TKey : mixed) $value
     *
     * @return ($hashContext is null ? int : null)
     */
    protected function makeHashFromKey($value, \HashContext $hashContext = null): ?int
    {
        if ($hashContext === null) {
            $hashContext = hash_init('crc32c');
            $return = true;
        } else {
            $return = false;
        }

        $this->makeHashFromKeyUpdate($hashContext, gettype($value));

        if (is_array($value)) {
            $this->makeHashFromKeyUpdate($hashContext, (string) count($value));
            foreach ($value as $k => $v) {
                $this->makeHashFromKeyUpdate($hashContext, (string) $k);
                $this->makeHashFromKey($v, $hashContext);
            }
        } else {
            if (is_object($value)) {
                $value = spl_object_id($value);
            } elseif (is_resource($value)) {
                $value = get_resource_id($value);
            }

            $this->makeHashFromKeyUpdate(
                $hashContext,
                is_float($value)
                    ? Expression::castFloatToString($value)
                    : (string) $value
            );
        }

        if (!$return) {
            return null;
        }

        $hex = hash_final($hashContext);
        if (\PHP_INT_SIZE === 4) {
            $hex = dechex(hexdec(substr($hex, 0, 4)) & ((1 << 15) - 1)) . substr($hex, 4, 4);
        }

        return hexdec($hex);
    }

    /**
     * @template T of TKey|TValue
     *
     * @param T $value
     *
     * @return (T is object ? T : WeakAnalysingBoxedArray<T>)
     */
    protected function boxValue($value): object
    {
        return is_array($value)
            ? new WeakAnalysingBoxedArray($value)
            : $value;
    }

    /**
     * @template T of TKey|TValue
     *
     * @param (T is object ? T : WeakAnalysingBoxedArray<T>) $value
     *
     * @return T
     */
    protected function unboxValue(object $value)
    {
        return $value instanceof WeakAnalysingBoxedArray
            ? $value->get()
            : $value;
    }

    /**
     * @param TOwner $owner
     */
    protected function addKeyOwner(object $owner, int $hash, int $index): void
    {
        if (!$this->ownerDestructorHandlers->offsetExists($owner)) {
            $this->ownerDestructorHandlers->offsetSet($owner, new class($this, $this->destructedEarly) {
                /** @var \WeakReference<WeakAnalysingMap<TKey, TValue, TOwner>> */
                private \WeakReference $weakAnalysingMap;
                /** @var \WeakReference<WeakAnalysingBoxedArray<array{}>> */
                private \WeakReference $weakAnalysingMapDestructedEarly;

                /** @var array<int, array{int, (TKey is object ? TKey : WeakAnalysingBoxedArray<TKey>), (TValue is object ? TValue : WeakAnalysingBoxedArray<TValue>)}> */
                private array $referencesByIndex = [];

                /**
                 * @param WeakAnalysingMap<TKey, TValue, TOwner> $analysingMap
                 * @param WeakAnalysingBoxedArray<array{}>       $destructedEarly
                 */
                public function __construct(WeakAnalysingMap $analysingMap, WeakAnalysingBoxedArray $destructedEarly)
                {
                    $this->weakAnalysingMap = \WeakReference::create($analysingMap);
                    $this->weakAnalysingMapDestructedEarly = \WeakReference::create($destructedEarly);
                }

                public function __destruct()
                {
                    if ($this->weakAnalysingMapDestructedEarly->get() === null) {
                        return;
                    }

                    $analysingMap = $this->weakAnalysingMap->get();
                    if ($analysingMap === null) {
                        return;
                    }

                    $referencesByIndex = $this->referencesByIndex;
                    \Closure::bind(static function () use ($analysingMap, $referencesByIndex) {
                        foreach ($referencesByIndex as $index => [$hash]) {
                            $ownerCount = --$analysingMap->valueWithOwnerCountByIndex[$index][1];
                            if ($ownerCount === 0) {
                                unset($analysingMap->keyByIndexByHash[$hash][$index]);
                                if ($analysingMap->keyByIndexByHash[$hash] === []) {
                                    unset($analysingMap->keyByIndexByHash[$hash]);
                                }
                                unset($analysingMap->valueWithOwnerCountByIndex[$index]);
                            }
                        }
                    }, null, WeakAnalysingMap::class)();
                }

                public function addReference(int $hash, int $index): void
                {
                    if (!isset($this->referencesByIndex[$index])) {
                        $analysingMap = $this->weakAnalysingMap->get();

                        $this->referencesByIndex[$index] = \Closure::bind(static function () use ($analysingMap, $hash, $index) {
                            ++$analysingMap->valueWithOwnerCountByIndex[$index][1];

                            return [
                                $hash,
                                $analysingMap->keyByIndexByHash[$hash][$index]->get(),
                                $analysingMap->valueWithOwnerCountByIndex[$index][0]->get(),
                            ];
                        }, null, WeakAnalysingMap::class)();
                    }
                }
            });
        }

        $this->ownerDestructorHandlers->offsetGet($owner)
            ->addReference($hash, $index);
    }

    /**
     * @param TKey $key
     *
     * @return TValue|null
     */
    public function get($key, object $owner)
    {
        $hash = $this->makeHashFromKey($key);

        foreach ($this->keyByIndexByHash[$hash] ?? [] as $index => $k) {
            if ($this->unboxValue($k->get()) === $key) {
                $value = $this->unboxValue($this->valueWithOwnerCountByIndex[$index][0]->get());

                $this->addKeyOwner($owner, $hash, $index);

                return $value;
            }
        }

        return null;
    }

    /**
     * @param TKey   $key
     * @param TValue $value
     * @param TOwner $owner
     */
    public function set($key, $value, object $owner): void
    {
        $hash = $this->makeHashFromKey($key);

        foreach ($this->keyByIndexByHash[$hash] ?? [] as $index => $k) {
            if ($this->unboxValue($k->get()) === $key) {
                throw (new Exception('Analysing key is already present'))
                    ->addMoreInfo('key', $key);
            }
        }

        $keyBoxed = $this->boxValue($key);
        $valueBoxed = $this->boxValue($value);

        $index = ++$this->indexCounter;
        $this->keyByIndexByHash[$hash][$index] = \WeakReference::create($keyBoxed);
        $this->valueWithOwnerCountByIndex[$index] = [\WeakReference::create($valueBoxed), 0];

        $this->addKeyOwner($owner, $hash, $index);
    }
}
