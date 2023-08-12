<?php

declare(strict_types=1);

namespace Atk4\Data\Type;

class LocalObjectHandle
{
    private int $localUid;

    /** @var \WeakReference<object> */
    private \WeakReference $weakValue;

    /**
     * @var \Closure($this): void
     */
    private \Closure $destructFx;

    /**
     * @param \Closure($this): void $destructFx
     */
    public function __construct(int $localUid, object $value, \Closure $destructFx)
    {
        $this->localUid = $localUid;
        $this->weakValue = \WeakReference::create($value);
        $this->destructFx = $destructFx;
    }

    public function __destruct()
    {
        ($this->destructFx)($this);
    }

    public function getLocalUid(): int
    {
        return $this->localUid;
    }

    public function getValue(): ?object
    {
        return $this->weakValue->get();
    }
}
