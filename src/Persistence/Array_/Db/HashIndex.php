<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

class HashIndex
{
    /** @var array<int|string, array<int, true>> */
    private array $data = [];

    /**
     * @param scalar|null $value
     *
     * @return int|string
     */
    protected function makeIndexKeyFromValue($value)
    {
        assert(is_scalar($value) || $value === null); // @phpstan-ignore identical.alwaysTrue, booleanOr.alwaysTrue, function.alreadyNarrowedType

        if (is_float($value)) {
            $value = $value === (float) (int) $value
                ? (int) $value
                : pack('e', $value);
        }

        return is_int($value)
            ? $value
            : (string) $value;
    }

    /**
     * @param scalar|null $value
     */
    protected function addRow(int $rowIndex, $value): void
    {
        $ik = $this->makeIndexKeyFromValue($value);

        $this->data[$ik][$rowIndex] = true;
    }

    /**
     * @param scalar|null $value
     */
    protected function deleteRow(int $rowIndex, $value): void
    {
        $ik = $this->makeIndexKeyFromValue($value);

        if (isset($this->data[$ik])) {
            unset($this->data[$ik][$rowIndex]);
            if ($this->data[$ik] === []) {
                unset($this->data[$ik]);
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
        $ik = $this->makeIndexKeyFromValue($value);

        return array_keys($this->data[$ik] ?? []);
    }
}
