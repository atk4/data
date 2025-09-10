<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

use Atk4\Data\Persistence\Sql\Expression;

class HashIndex
{
    /** @var array<int|string, array<int, true>> */
    private array $data = [];

    /**
     * @param scalar|null $value
     *
     * @return int|string
     */
    protected function makeKeyFromValue($value)
    {
        assert(is_scalar($value) || $value === null); // @phpstan-ignore identical.alwaysTrue, booleanOr.alwaysTrue, function.alreadyNarrowedType

        if (is_float($value)) {
            $value = Expression::castFloatToString($value);
            if (str_ends_with($value, '.0')) {
                $value = substr($value, 0, -2);
            }
        } elseif ($value === false) {
            $value = 0;
        }

        if (!is_int($value)) {
            $value = (string) $value;

            if ($value === (string) (int) $value) {
                $value = (int) $value;
            }
        }

        return $value;
    }

    /**
     * @param scalar|null $value
     */
    protected function addRow(int $rowIndex, $value): void
    {
        $key = $this->makeKeyFromValue($value);

        $this->data[$key][$rowIndex] = true;
    }

    /**
     * @param scalar|null $value
     */
    protected function deleteRow(int $rowIndex, $value): void
    {
        $key = $this->makeKeyFromValue($value);

        if (isset($this->data[$key])) {
            unset($this->data[$key][$rowIndex]);
            if ($this->data[$key] === []) {
                unset($this->data[$key]);
            }
        }
    }

    /**
     * @param scalar|null $value
     *
     * @return list<int>
     */
    public function findPossibleRowIndexes($value): array
    {
        $key = $this->makeKeyFromValue($value);

        return array_keys($this->data[$key] ?? []);
    }
}
