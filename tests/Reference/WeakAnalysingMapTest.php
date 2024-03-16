<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Reference;

use Atk4\Data\Exception;
use Atk4\Data\Reference\WeakAnalysingMap;
use Atk4\Data\Schema\TestCase;

class WeakAnalysingMapTest extends TestCase
{
    /**
     * @param WeakAnalysingMap<object|array<mixed>, object|array<mixed>, object> $map
     */
    private function forceWeakMapPolyfillHousekeeping(WeakAnalysingMap $map): void
    {
        // https://github.com/BenMorel/weakmap-polyfill/blob/0.4.0/src/WeakMap.php#L126
        $weakMap = \Closure::bind(static fn () => $map->ownerDestructorHandlers, null, WeakAnalysingMap::class)();
        count($weakMap); // @phpstan-ignore-line
    }

    public function testBasic(): void
    {
        $map = new WeakAnalysingMap();

        self::assertNull($map->get(new \stdClass(), $map));
        self::assertNull($map->get(new \stdClass(), new \stdClass()));

        $key1 = new \stdClass();
        $key2 = new \stdClass();
        $key3 = new \stdClass();
        $value1 = new \stdClass();
        $value2 = new \stdClass();
        $value3 = new \stdClass();
        $owner1 = new \stdClass();
        $owner2 = new \stdClass();
        $weakKey = \WeakReference::create($key1);
        $weakValue = \WeakReference::create($value1);
        $weakOwner = \WeakReference::create($owner1);

        $map->set($key1, $value1, $owner1);
        $map->set($key2, $value2, $owner1);
        $map->set($key3, $value3, $owner2);
        self::assertSame($value1, $map->get($key1, $owner1));
        self::assertSame($value2, $map->get($key2, $owner1));
        self::assertSame($value3, $map->get($key3, $owner2));
        self::assertSame($value1, $map->get($key1, new \stdClass()));
        self::assertSame($value2, $map->get($key2, $owner2));

        unset($owner1);
        $this->forceWeakMapPolyfillHousekeeping($map);
        self::assertNull($map->get($key1, new \stdClass()));
        self::assertSame($value2, $map->get($key2, new \stdClass()));
        self::assertSame($value3, $map->get($key3, new \stdClass()));

        unset($owner2);
        $this->forceWeakMapPolyfillHousekeeping($map);
        self::assertNull($map->get($key1, new \stdClass()));
        self::assertNull($map->get($key2, new \stdClass()));
        self::assertNull($map->get($key3, new \stdClass()));

        unset($key1);
        unset($value1);
        self::assertNull($weakKey->get());
        self::assertNull($weakValue->get());
        self::assertNull($weakOwner->get());
    }

    public function testBoxedArray(): void
    {
        $map = new WeakAnalysingMap();

        $key1 = [];
        $key2 = [null, false, true, 1, 1.5, 'foo', "\x00", "\xff"];
        $key3 = [new \stdClass(), fopen('php://memory', 'r+')];
        $value1 = [];
        $value2 = [null];
        $value3 = [new \stdClass()];
        $owner1 = new \stdClass();
        $owner2 = new \stdClass();
        $weakKeyObject = \WeakReference::create($key3[0]);
        $weakValueObject = \WeakReference::create($value3[0]);

        $map->set($key1, $value1, $owner1);
        $map->set($key2, $value2, $owner1);
        $map->set($key3, $value3, $owner2);
        self::assertSame($value1, $map->get($key1, $owner1));
        self::assertSame($value2, $map->get($key2, $owner1));
        self::assertSame($value3, $map->get($key3, $owner2));

        unset($owner1);
        $this->forceWeakMapPolyfillHousekeeping($map);
        self::assertNull($map->get($key1, new \stdClass()));
        self::assertNull($map->get($key2, new \stdClass()));
        self::assertSame($value3, $map->get($key3, new \stdClass()));

        unset($owner2);
        $this->forceWeakMapPolyfillHousekeeping($map);
        self::assertNull($map->get($key1, new \stdClass()));
        self::assertNull($map->get($key2, new \stdClass()));
        self::assertNull($map->get($key3, new \stdClass()));

        unset($key3);
        unset($value3);
        self::assertNull($weakKeyObject->get());
        self::assertNull($weakValueObject->get());
    }

    public function testDestructBeforeKey(): void
    {
        $key = new \stdClass();

        (new WeakAnalysingMap())
            ->set($key, new \stdClass(), new \stdClass());

        $weakKey = \WeakReference::create($key);
        unset($key);
        self::assertNull($weakKey->get());
    }

    public function testDestructBeforeOwner(): void
    {
        $owner = new \stdClass();

        (new WeakAnalysingMap())
            ->set(new \stdClass(), new \stdClass(), $owner);

        $weakOwner = \WeakReference::create($owner);
        unset($owner);
        self::assertNull($weakOwner->get());
    }

    public function testSetKeyAlreadyPresentException(): void
    {
        $map = new WeakAnalysingMap();

        $key = new \stdClass();
        $value = new \stdClass();
        $owner = new \stdClass();

        $map->set($key, $value, $owner);

        $e = false;
        try {
            $map->set($key, $value, $owner);
        } catch (Exception $e) {
            $e = $e->getMessage();
        } finally {
            self::assertSame($value, $map->get($key, $owner));

            unset($owner);
            $this->forceWeakMapPolyfillHousekeeping($map);
            self::assertNull($map->get($key, $key));
        }
        self::assertSame('Analysing key is already present', $e);
    }

    public function testGetSetKeyWithHashCollision(): void
    {
        $map = new WeakAnalysingMap();

        $makeHashFromKeyFx = \Closure::bind(static fn ($v) => $map->makeHashFromKey($v), null, WeakAnalysingMap::class);

        $hashes = array_map(static fn ($v) => $makeHashFromKeyFx([$v]), range(1, 2_000));
        self::assertSameSize($hashes, array_unique($hashes));

        $key1 = [5_371_838];
        $key2 = [6_000_402];
        self::assertSame($makeHashFromKeyFx($key1), $makeHashFromKeyFx($key2));

        $value1 = new \stdClass();
        $value2 = new \stdClass();
        $map->set($key1, $value1, $map);
        $map->set($key2, $value2, $map);
        self::assertSame($value1, $map->get($key1, new \stdClass()));
        self::assertSame($value2, $map->get($key2, new \stdClass()));
    }
}
