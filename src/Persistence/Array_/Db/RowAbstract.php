<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

abstract class RowAbstract
{
    public function __construct(TableAbstract $parentTable) {}

    /**
     * @param string|int $columnName
     */
    abstract public function getValue($columnName);

    /**
     * @return array<string|int, mixed>
     */
    abstract public function getData(): array;

    protected function beforeDelete(): void
    {
        $this->parentTable = null; // @phpstan-ignore-line
    }
}
