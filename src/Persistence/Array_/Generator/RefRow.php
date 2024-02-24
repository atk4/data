<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Generator;

class RefRow
{
    /** @var string Immutable */
    private $tableName;
    /** @var int Immutable */
    private $rowIndex;

    public function __construct(string $tableName, int $rowIndex)
    {
        $this->tableName = $tableName;
        $this->rowIndex = $rowIndex;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }
}
