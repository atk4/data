<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

class Row
{
    private const DATA_DELETE = [self::class . '@delete'];

    private static int $lastRowIndex = -1;

    /** @readonly */
    private ?Table $owner;
    /** @readonly */
    private int $rowIndex;
    /** @var array<string, mixed> */
    private array $data = [];

    protected function __construct(Table $owner)
    {
        $this->owner = $owner;
        $this->rowIndex = ++self::$lastRowIndex;
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        return [
            'row_index' => $this->getRowIndex(),
            'data' => $this->getData(),
        ];
    }

    public function getOwner(): Table
    {
        return $this->owner;
    }

    public function getRowIndex(): int
    {
        return $this->rowIndex;
    }

    /**
     * @return mixed
     */
    public function getValue(string $columnName)
    {
        return $this->data[$columnName];
    }

    /**
     * @return array<string, mixed>
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * @param array<string, mixed>|self::DATA_DELETE $data
     */
    public function updateValues(array $data): void
    {
        $owner = $this->getOwner();

        $newData = [];
        if ($data === self::DATA_DELETE) {
            $oldData = $this->data;
        } else {
            $oldData = [];
            foreach ($data as $columnName => $newValue) {
                $oldValue = $this->data[$columnName] ?? null;
                $hadColumn = $oldValue !== null || array_key_exists($columnName, $this->data);
                if (!$hadColumn || $newValue !== $oldValue) {
                    if (!$hadColumn) {
                        $owner->assertHasColumn($columnName);
                    } else {
                        $oldData[$columnName] = $oldValue;
                    }
                    $newData[$columnName] = $newValue;
                }
            }

            if ($newData === []) {
                return;
            }
        }

        $thisRow = $this;
        \Closure::bind(static function () use ($owner, $thisRow, $newData) {
            $owner->beforeUpdateRow($thisRow, $newData);
        }, null, Table::class)();

        if ($data === self::DATA_DELETE) {
            $this->data = [];
        } else {
            foreach ($newData as $columnName => $newValue) {
                $this->data[$columnName] = $newValue;
            }
        }

        \Closure::bind(static function () use ($owner, $thisRow, $oldData, $newData) {
            $owner->afterUpdateRow($thisRow, $oldData, $newData);
        }, null, Table::class)();
    }

    protected function delete(): void
    {
        $this->updateValues(self::DATA_DELETE);
        $this->owner = null; // @phpstan-ignore property.readOnlyByPhpDocAssignNotInConstructor
    }
}
