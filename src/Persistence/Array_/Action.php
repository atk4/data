<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;

/**
 * Returned by Model::action(). Compatible with DSQL to a certain point as it implements
 * specific methods such as getOne() or getRows().
 */
class Action
{
    /** @var \Iterator<int, non-empty-array<string, mixed>> */
    public \Iterator $generator;

    /** @var non-empty-list<string> */
    private array $columns;

    /** @var list<\Closure(array<string, mixed>): bool> hack for GC for PHP 8.1.3 or older */
    private array $_filterFxs = [];

    /**
     * @param list<non-empty-array<string, mixed>> $data
     * @param non-empty-list<string>               $columns
     */
    public function __construct(array $data, array $columns)
    {
        assert($columns !== []); // @phpstan-ignore function.alreadyNarrowedType, notIdentical.alwaysTrue

        $this->generator = new \ArrayIterator($data);
        $this->columns = $columns;
    }

    /**
     * @return non-empty-list<string>
     */
    public function getColumns(): array
    {
        return $this->columns;
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value.
     *
     * @return $this
     */
    public function filter(Model\Scope\AbstractScope $condition)
    {
        if (!$condition->isEmpty()) {
            // CallbackFilterIterator with circular reference (bound function) is not GCed
            // https://github.com/php/php-src/commit/afab9eb48c
            // https://github.com/php/php-src/commit/fb70460d8e
            // remove the if below once PHP 8.1.3 (or older) is no longer supported
            $filterFx = function (array $row) use ($condition): bool {
                return $this->match($row, $condition);
            };
            if (\PHP_VERSION_ID < 8_01_04 && count($this->_filterFxs) !== \PHP_INT_MAX) {
                $this->_filterFxs[] = $filterFx; // prevent filter function to be GCed
                $filterFxWeakRef = \WeakReference::create($filterFx);
                $this->generator = new \CallbackFilterIterator($this->generator, static function (array $row) use ($filterFxWeakRef) {
                    return $filterFxWeakRef->get()($row);
                });
            } else {
                $this->generator = new \CallbackFilterIterator($this->generator, $filterFx);
            }
            // initialize filter iterator, it is not rewound by default
            // https://github.com/php/php-src/issues/7952
            $this->generator->rewind();
        }

        return $this;
    }

    /**
     * @param scalar|null $value
     */
    private function setScalarResult($value): void
    {
        $this->generator = new \ArrayIterator([['v' => $value]]);
        $this->columns = ['v'];
    }

    /**
     * Calculates SUM|AVG|MIN|MAX aggregate values for $field.
     *
     * @return $this
     */
    public function aggregate(string $fx, string $field, bool $nullAsZero = false)
    {
        $fx = strtolower($fx);

        $valuesAll = array_column($this->getRows(), $field);
        $values = array_values(array_filter($valuesAll, static fn ($v) => $v !== null));

        if (count($values) === 0 || ($values !== $valuesAll && $fx !== 'json-array' && !$nullAsZero)) {
            $this->setScalarResult($nullAsZero ? ($fx === 'json-array' ? '[]' : 0) : null);

            return $this;
        }

        switch ($fx) {
            case 'count':
                $res = count($values);

                break;
            case 'sum':
                $res = array_sum($values);

                break;
            case 'avg':
                $res = array_sum($values) / count($values);

                break;
            case 'min':
                $res = min($values);

                break;
            case 'max':
                $res = max($values);

                break;
            case 'median':
                sort($values);
                $i = (int) floor((count($values) - 1) / 2);
                $j = (int) floor(count($values) / 2);
                $res = ($values[$i] + $values[$j]) / 2;

                break;
            case 'stddev_pop':
            case 'stddev_samp':
                $sum = 0;
                $avg = array_sum($values) / count($values);
                foreach ($values as $v) {
                    $sum += ($v - $avg) ** 2;
                }
                $resSquared = $sum / (count($values) - ($fx === 'stddev_samp' ? 1 : 0));
                $res = sqrt($resSquared);
                if ($resSquared === (int) $res * (int) $res) {
                    $res = (int) $res;
                }

                break;
            case 'json-array':
                $res = json_encode($values, \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

                break;
            default:
                throw (new Exception('Array persistence unsupported aggregate function'))
                    ->addMoreInfo('action', $fx);
        }

        $this->setScalarResult($res);

        return $this;
    }

    /**
     * Checks if $row matches $condition.
     *
     * @param non-empty-array<string, mixed> $row
     */
    protected function match(array $row, Model\Scope\AbstractScope $condition): bool
    {
        if ($condition instanceof Model\Scope\Condition) { // simple condition
            $args = $condition->toQueryArguments();

            $field = $args[0];
            $operator = $args[1] ?? null;
            $value = $args[2] ?? null;
            if ($operator === null) {
                $operator = '=';
            }

            if (!is_a($field, Field::class)) {
                throw (new Exception('Array persistence driver condition unsupported format'))
                    ->addMoreInfo('reason', 'Unsupported object instance ' . get_class($field))
                    ->addMoreInfo('condition', $condition);
            }

            return $this->evaluateIf($row[$field->shortName], $operator, $value);
        } elseif ($condition instanceof Model\Scope) { // nested conditions
            $isOr = $condition->isOr();
            $nestedConditions = $condition->getNestedConditions();

            $res = $nestedConditions === [] || !$isOr;
            foreach ($nestedConditions as $nestedCondition) {
                $submatch = $this->match($row, $nestedCondition);

                if ($isOr && $submatch) {
                    $res = true;

                    break; // short-circuit
                } elseif (!$isOr && !$submatch) {
                    $res = false;

                    break; // short-circuit
                }
            }

            return $res;
        }

        throw (new Exception('Unexpected condition type'))
            ->addMoreInfo('class', get_class($condition));
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function tryConvertTableToValue($value, bool $toArray)
    {
        if (is_array($value)) {
            $values = [];
            foreach ($value as $k => $row) {
                if (!is_array($row)) {
                    return $value;
                }

                if (count($row) !== 1) {
                    throw (new Exception('Unable to get value from table with more than 1 column'))
                        ->addMoreInfo('table', $value);
                }

                $values[$k] = array_first($row);
            }

            if ($toArray) {
                return $values;
            }

            if ($values === []) {
                return null;
            } elseif (count($values) === 1) {
                return array_first($values);
            }

            throw (new Exception('Unable to get value from table with more than 1 row'))
                ->addMoreInfo('table', $value);
        }

        return $value;
    }

    /**
     * @param mixed            $v1
     * @param non-empty-string $operator
     * @param mixed            $v2
     */
    protected function evaluateIf($v1, string $operator, $v2): bool
    {
        if ($v2 instanceof self) {
            $v2 = $v2->getRows();
        }

        if ($v2 instanceof \Traversable) {
            throw (new Exception('Unexpected v2 type'))
                ->addMoreInfo('class', get_class($v2));
        }

        $v2Array = in_array(strtoupper($operator), ['IN', 'NOT IN'], true);

        $v1 = $this->tryConvertTableToValue($v1, false);
        $v2 = $this->tryConvertTableToValue($v2, $v2Array);

        foreach ([$v1, ...($v2Array ? $v2 : [$v2])] as $v) {
            if ($v === null) {
                if (in_array($operator, ['=', '!='], true)) {
                    continue;
                }

                throw (new Exception('Unsupported operator for null value'))
                    ->addMoreInfo('operator', $operator);
            }

            if (!is_scalar($v)) {
                throw (new Exception('Only scalar values can be compared'))
                    ->addMoreInfo('value', $v);
            }
        }

        switch (strtoupper($operator)) {
            case '=':
                $res = $v1 === $v2;

                break;
            case '!=':
                $res = !$this->evaluateIf($v1, '=', $v2);

                break;
            case '>':
                $res = $v1 > $v2;

                break;
            case '>=':
                $res = $v1 >= $v2;

                break;
            case '<':
                $res = $v1 < $v2;

                break;
            case '<=':
                $res = $v1 <= $v2;

                break;
            case 'IN':
                $res = false;
                foreach ($v2 as $v2Item) {
                    if ($this->evaluateIf($v1, '=', $v2Item)) {
                        $res = true;

                        break;
                    }
                }

                break;
            case 'NOT IN':
                $res = !$this->evaluateIf($v1, 'IN', $v2);

                break;
            case 'LIKE':
                $pattern = str_replace('_', '(.)', str_replace('%', '(.*)', preg_quote($v2, '~')));

                $res = preg_match('~^' . $pattern . '$~is', (string) $v1) === 1;

                break;
            case 'NOT LIKE':
                $res = !$this->evaluateIf($v1, 'LIKE', $v2);

                break;
            case 'REGEXP':
                $pattern = preg_replace('~(?<!\\\)(?:\\\\\\\)*+\K\~~', '\\\~', $v2);

                $res = preg_match('~' . $pattern . '~is', $v1) === 1;

                break;
            case 'NOT REGEXP':
                $res = !$this->evaluateIf($v1, 'REGEXP', $v2);

                break;
            default:
                throw (new Exception('Unsupported operator'))
                    ->addMoreInfo('operator', $operator);
        }

        return $res;
    }

    /**
     * Applies sorting on Iterator.
     *
     * @param list<array{string, 'asc'|'desc'}> $fields
     *
     * @return $this
     */
    public function order(array $fields)
    {
        $data = $this->getRows();

        $multisortArgs = [];
        foreach ($fields as [$field, $direction]) {
            $multisortArgs[] = array_column($data, $field);
            $multisortArgs[] = strtolower($direction) === 'desc' ? \SORT_DESC : \SORT_ASC;
            if (array_last($multisortArgs) === \SORT_ASC) {
                assert(strtolower($direction) === 'asc');
            }
        }

        array_multisort(...$multisortArgs, ...[&$data]);

        $this->generator = new \ArrayIterator($data);

        return $this;
    }

    /**
     * Limit Iterator.
     *
     * @return $this
     */
    public function limit(?int $limit, int $offset = 0)
    {
        $this->generator = new \LimitIterator($this->generator, $offset, $limit ?? -1);

        return $this;
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     *
     * @return $this
     */
    public function count()
    {
        $this->setScalarResult(iterator_count($this->generator));

        return $this;
    }

    /**
     * Checks if iterator has any rows.
     *
     * @return $this
     */
    public function exists()
    {
        $this->generator->rewind();
        $this->setScalarResult($this->generator->valid() ? 1 : 0);

        return $this;
    }

    /**
     * @param non-empty-list<string> $columns
     */
    private function assertExpectedColumns(array $columns): void
    {
        $expected = $this->getColumns();

        if ($columns !== $expected) {
            throw (new Exception('Column names mismatch'))
                ->addMoreInfo('actual', $columns)
                ->addMoreInfo('expected', $expected);
        }
    }

    /**
     * Return all data inside array.
     *
     * @return list<non-empty-array<string, mixed>>
     */
    public function getRows(): array
    {
        $res = iterator_to_array($this->generator, false);

        foreach ($res as $row) {
            $this->assertExpectedColumns(array_keys($row));
        }

        return $res;
    }

    /**
     * Return one row of data.
     *
     * @return non-empty-array<string, mixed>|null
     */
    public function getRow(): ?array
    {
        $this->generator->rewind(); // TODO alternatively allow to fetch only once
        $row = $this->generator->current();
        $this->generator->next();

        $this->assertExpectedColumns(array_keys($row));

        return $row;
    }

    /**
     * Return one value from one row of data.
     *
     * @return mixed
     */
    public function getOne()
    {
        $data = $this->getRow();

        return array_first($data);
    }
}
