<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

/**
 * @internal
 *
 * @template T of array<mixed>
 */
class WeakAnalysingBoxedArray
{
    /** @var T */
    private array $value;

    /**
     * @param T $value
     */
    public function __construct(array $value)
    {
        $this->value = $value;
    }

    /**
     * @return T
     */
    public function get(): array
    {
        return $this->value;
    }
}
