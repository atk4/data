<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Generator;

class RefColumn
{
    /** @var string Immutable */
    private $tableName;
    /** @var string|int Immutable */
    private $columnName;

    /**
     * @param string|int $columnName
     */
    public function __construct(string $tableName, $columnName)
    {
        $this->tableName = $tableName;
        $this->columnName = $columnName;
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @return string|int
     */
    public function getColumnName()
    {
        return $this->columnName;
    }
}
