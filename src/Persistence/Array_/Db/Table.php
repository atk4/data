<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

use Atk4\Data\Exception;
use Atk4\Data\Model;

class Table
{
    /** @readonly */
    private string $tableName;
    /** @var array<string, string> */
    private array $columnNames = [];
    /** @var array<int, Row> */
    private array $rows = [];

    public function __construct(string $tableName)
    {
        $this->tableName = $tableName;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'table_name' => $this->getTableName(),
            'column_names' => $this->getColumnNames(),
            'row_count' => count($this->getRows()),
        ];
    }

    /**
     * @param string $name
     */
    protected function assertValidName($name): void
    {
        if (!is_string($name) || $name === '' || is_numeric($name)) { // @phpstan-ignore function.alreadyNarrowedType
            throw (new Exception('Name must be a non-empty non-numeric string'))
                ->addMoreInfo('name', $name);
        }
    }

    /**
     * @param mixed $value
     */
    protected function assertValidValue($value): void
    {
        if (!is_scalar($value) && $value !== null) {
            throw (new Exception('Value must be scalar'))
                ->addMoreInfo('value', $value);
        }
    }

    public function getTableName(): string
    {
        return $this->tableName;
    }

    public function hasColumn(string $columnName): bool
    {
        return isset($this->columnNames[$columnName]);
    }

    public function assertHasColumn(string $columnName): void
    {
        if (!$this->hasColumn($columnName)) {
            throw (new Exception('Column name does not exist'))
                ->addMoreInfo('table_name', $this->getTableName())
                ->addMoreInfo('column_name', $columnName);
        }
    }

    /**
     * @return list<string>
     */
    public function getColumnNames(): array
    {
        return array_values($this->columnNames);
    }

    /**
     * @return $this
     */
    public function addColumn(string $columnName): self
    {
        $this->assertValidName($columnName);

        if ($this->hasColumn($columnName)) {
            throw (new Exception('Column name is already present'))
                ->addMoreInfo('table_name', $this->getTableName())
                ->addMoreInfo('column_name', $columnName);
        }

        $this->columnNames[$columnName] = $columnName;

        foreach ($this->getRows() as $row) {
            $row->updateValues([$columnName => null]);
        }

        return $this;
    }

    public function hasRow(int $rowIndex): bool
    {
        return isset($this->rows[$rowIndex]);
    }

    public function getRow(int $rowIndex): Row
    {
        $row = $this->rows[$rowIndex] ?? null;

        if ($row === null) {
            throw (new Exception('Row with given index was not found'))
                ->addMoreInfo('table_name', $this->getTableName())
                ->addMoreInfo('row_index', $rowIndex);
        }

        return $row;
    }

    /**
     * @return \Iterator<Row>&\Countable
     */
    public function getRows(): \Iterator
    {
        return new \ArrayIterator($this->rows);
    }

    /**
     * @param class-string<Row>    $rowClass
     * @param array<string, mixed> $data
     */
    public function addRow(string $rowClass, array $data): Row
    {
        foreach ($data as $columnName => $value) {
            if (!$this->hasColumn($columnName)) {
                $this->addColumn($columnName);
            }
        }

        $thisTable = $this;
        $row = \Closure::bind(static fn () => new $rowClass($thisTable), null, Row::class)();
        $this->rows[$row->getRowIndex()] = $row;
        $row->updateValues(array_merge(array_fill_keys($this->getColumnNames(), null), $data));

        return $row;
    }

    public function deleteRow(Row $row): void
    {
        \Closure::bind(static function () use ($row) {
            $row->delete();
        }, null, Row::class)();

        unset($this->rows[$row->getRowIndex()]);
    }

    /**
     * @param array<string, mixed> $newData
     */
    protected function beforeUpdateRow(Row $row, $newData): void
    {
        foreach ($newData as $columnName => $newValue) {
            $this->assertValidValue($newValue);
        }
    }

    /**
     * @param array<string, mixed> $oldData
     */
    protected function afterUpdateRow(Row $row, $oldData): void
    {
        foreach ($oldData as $columnName => $newValue) {
            // update index here
        }
    }

    /**
     * TODO rewrite with hash index support.
     *
     * @param scalar $idRaw
     */
    public function getRowById(Model $model, $idRaw): ?Row
    {
        $idFieldRaw = $model->getIdField()->getPersistenceName();

        foreach ($this->getRows() as $row) {
            if ($row->getValue($idFieldRaw) === $idRaw) {
                return $row;
            }
        }

        return null;
    }
}
