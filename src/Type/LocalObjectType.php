<?php

declare(strict_types=1);

namespace Atk4\Data\Type;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types as DbalTypes;

/**
 * Type that allows to weak reference a local PHP object using a scalar string.
 */
class LocalObjectType extends DbalTypes\Type
{
    private ?string $localUidPrefix = null;

    private int $localUidCounter;

    /** @var \WeakMap<object, LocalObjectHandle> */
    private \WeakMap $handles;
    /** @var array<int, \WeakReference<LocalObjectHandle>> */
    private array $handlesIndex;

    protected function __clone()
    {
        // prevent clonning
    }

    protected function init(): void
    {
        $this->localUidPrefix = hash('sha256', microtime(true) . random_bytes(64));
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

        if ($this->localUidPrefix === null) {
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

        return $this->localUidPrefix . '-' . $handle->getLocalUid();
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?object
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $handleLocalUid = $this->localUidPrefix !== null && str_starts_with($value, $this->localUidPrefix . '-')
            ? substr($value, strlen($this->localUidPrefix . '-'))
            : null;
        if ($handleLocalUid !== null && $handleLocalUid !== (string) (int) $handleLocalUid) {
            throw new Exception('Local object does not match the DBAL type instance');
        }
        $handle = $this->handlesIndex[(int) $handleLocalUid] ?? null;
        if ($handle !== null) {
            $handle = $handle->get();
        }
        if ($handle === null) {
            throw new Exception('Local object does no longer exist');
        }

        return $handle->getValue();
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
