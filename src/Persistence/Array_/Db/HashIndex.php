<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

class HashIndex
{
    /** @var TableArray Immutable */
    private $parentTable;
    /** @var string Immutable */
    private $columnName;
    /** @var bool Immutable */
    private $isUnique;
    /** @var array<string, array<int>> */
    private $rowIndexesByHash = [];

    public function __construct(TableArray $parentTable, string $columnName, bool $isUnique)
    {
        $this->parentTable = $parentTable;
        $this->columnName = $columnName;
        $this->isUnique = $isUnique;

        foreach ($this->parentTable->getRows() as $row) {
            // TODO
        }
    }

    public function getParentTable(): TableArray
    {
        return $this->parentTable;
    }

    public function getColumnName(): string
    {
        return $this->columnName;
    }

    public function makeHash(RowArray $row): string
    {
        return md5(json_encode($row->getValue($this->columnName)));
    }
}
