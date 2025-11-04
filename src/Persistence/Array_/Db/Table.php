<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

use Atk4\Data\Exception;
use Atk4\Data\Model;

class Table
{
    /** @readonly */
    private string $tableName;
    /** @var array<string, true> */
    private array $columnNames = [];
    /** @var array<int, Row> */
    private array $rows = [];

    /** @var array<string, HashIndex> */
    private array $indexes = [];

    public function __construct(string $tableName)
    {
        $this->assertValidName($tableName);

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
            throw (new Exception('Name must be a non-empty and non-numeric'))
                ->addMoreInfo('name', $name);
        }
    }

    /**
     * @param mixed $value
     */
    protected function assertValidValue($value): void
    {
        if (!is_scalar($value) && $value !== null) {
            throw (new Exception('Value must be scalar or null'))
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
        return array_keys($this->columnNames);
    }

    /**
     * @return $this
     */
    public function addColumn(string $columnName): self
    {
        $this->assertValidName($columnName);

        if ($this->hasColumn($columnName)) {
            throw (new Exception('Column name already exists'))
                ->addMoreInfo('table_name', $this->getTableName())
                ->addMoreInfo('column_name', $columnName);
        }

        $this->columnNames[$columnName] = true;

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
     * @param array<string, mixed> $newData
     */
    protected function afterUpdateRow(Row $row, $oldData, $newData): void
    {
        foreach ($oldData as $columnName => $oldValue) {
            $index = $this->indexes[$columnName] ?? null;
            if ($index === null) {
                continue;
            }

            \Closure::bind(static function () use ($index, $row, $oldValue) {
                $index->deleteRow($row->getRowIndex(), $oldValue);
            }, null, HashIndex::class)();
        }

        foreach ($newData as $columnName => $newValue) {
            $index = $this->indexes[$columnName] ?? null;
            if ($index === null) {
                continue;
            }

            \Closure::bind(static function () use ($index, $row, $newValue) {
                $index->addRow($row->getRowIndex(), $newValue);
            }, null, HashIndex::class)();
        }
    }

    protected function addIndex(string $columnName): void
    {
        assert(!isset($this->indexes[$columnName]));

        $index = new HashIndex();

        foreach ($this->getRows() as $row) {
            \Closure::bind(static function () use ($index, $row, $columnName) {
                $index->addRow($row->getRowIndex(), $row->getValue($columnName));
            }, null, HashIndex::class)();
        }

        $this->indexes[$columnName] = $index;
    }

    /**
     * @param scalar|null $value
     *
     * @return list<Row>
     */
    public function getRowsUsingIndex(string $columnName, $value): array
    {
        if (!isset($this->indexes[$columnName])) {
            $this->addIndex($columnName);
        }

        $index = $this->indexes[$columnName];
        $possibleRowIndexes = $index->findPossibleRowIndexes($value);

        $res = [];
        foreach ($possibleRowIndexes as $rowIndex) {
            $row = $this->rows[$rowIndex];
            $rowValue = $row->getValue($columnName);
            if ($rowValue === $value) {
                $res[] = $row;
            }
        }

        return $res;
    }

    /**
     * @param scalar|null $value
     */
    public function getRowUsingIndex(string $columnName, $value): ?Row
    {
        $rows = $this->getRowsUsingIndex($columnName, $value);

        if ($rows === []) {
            return null;
        } elseif (count($rows) === 1) {
            return $rows[0];
        }

        throw new Exception('Index is not unique, more than one row was found');
    }

    /**
     * @param scalar|null $idRaw
     */
    public function getRowById(Model $model, $idRaw): ?Row
    {
        $idFieldRaw = $model->getIdField()->getPersistenceName();

        return $this->getRowUsingIndex($idFieldRaw, $idRaw);
    }
}
