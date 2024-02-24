<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

abstract class ColumnAbstract
{
    /** @var TableAbstract Immutable */
    private $owner;
    /** @var string Immutable */
    private $columnName;
    /** @var HashIndex|null Immutable once set */
    private $index;

    public function __construct(TableAbstract $owner, string $columnName)
    {
        $owner->assertHasColumnName($columnName);

        $this->owner = $owner;
        $this->columnName = $columnName;
    }

    public function getOwner(): TableAbstract
    {
        return $this->columnName;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function hasIndex(): bool
    {
        return $this->index !== null;
    }

    public function getIndex(): HashIndex
    {
        return $this->index;
    }
}
