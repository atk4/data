<?php

declare(strict_types=1);

namespace Atk4\Data\Type;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types as DbalTypes;

/**
 * Type that allows to weakly reference a local PHP object using a scalar string
 * and get the original object instance back using the string.
 *
 * The local object is never serialized.
 *
 * An exception is thrown when getting an object from a string back and the original
 * object instance has been destroyed/released.
 */
class LocalObjectType extends DbalTypes\Type
{
    private ?string $instanceUid = null;

    private int $localUidCounter;

    /** @var \WeakMap<object, LocalObjectHandle> */
    private \WeakMap $handles;
    /** @var array<int, \WeakReference<LocalObjectHandle>> */
    private array $handlesIndex;

    private function __clone()
    {
        // prevent cloning
    }

    protected function init(): void
    {
        $this->instanceUid = hash('sha256', microtime(true) . random_bytes(64));
        $this->localUidCounter = 0;
        $this->handles = new \WeakMap();
        $this->handlesIndex = [];
    }

    public function getName(): string
    {
        return Types::LOCAL_OBJECT;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return DbalTypes\Type::getType(DbalTypes\Types::STRING)->getSQLDeclaration($fieldDeclaration, $platform);
    }

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($this->instanceUid === null) {
            $this->init();
        }

        $handle = $this->handles->offsetExists($value)
            ? $this->handles->offsetGet($value)
            : null;

        if ($handle === null) {
            $handle = new LocalObjectHandle(++$this->localUidCounter, $value, function (LocalObjectHandle $handle): void {
                unset($this->handlesIndex[$handle->getLocalUid()]);
            });
            $this->handles->offsetSet($value, $handle);
            $this->handlesIndex[$handle->getLocalUid()] = \WeakReference::create($handle);
        }

        $className = get_debug_type($value);
        if (strlen($className) > 160) { // keep result below 255 bytes
            $className = mb_strcut($className, 0, 80)
                . '...'
                . mb_strcut(substr($className, strlen(mb_strcut($className, 0, 80))), -80);
        }

        return $className . '-' . $this->instanceUid . '-' . $handle->getLocalUid();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?object
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $valueExploded = explode('-', $value, 3);
        if (count($valueExploded) !== 3
            || $valueExploded[1] !== $this->instanceUid
            || $valueExploded[2] !== (string) (int) $valueExploded[2]
        ) {
            throw new Exception('Local object does not match the DBAL type instance');
        }
        $handle = $this->handlesIndex[(int) $valueExploded[2]] ?? null;
        if ($handle !== null) {
            $handle = $handle->get();
        }
        $res = $handle !== null ? $handle->getValue() : null;
        if ($res === null) {
            throw new Exception('Local object does no longer exist');
        }

        return $res;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
