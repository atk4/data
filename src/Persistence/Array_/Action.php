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
    /** @var \Iterator<int, array<string, mixed>> */
    public $generator;

    /** @var list<\Closure(array<string, mixed>): bool> hack for GC for PHP 8.1.3 or older */
    private array $_filterFxs = [];

    /**
     * @param array<int, array<string, mixed>> $data
     */
    public function __construct(array $data)
    {
        $this->generator = new \ArrayIterator($data);
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
            // https://github.com/php/php-src/commit/afab9eb48c883766b7870f76f2e2b0a4bd575786
            // https://github.com/php/php-src/commit/fb70460d8e7593e32abdaaf8ae8849345d49c8fd
            // remove the if below once PHP 8.1.3 (or older) is no longer supported
            $filterFx = function (array $row) use ($condition): bool {
                return $this->match($row, $condition);
            };
            if (\PHP_VERSION_ID < 80104 && count($this->_filterFxs) !== \PHP_INT_MAX) {
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
     * Calculates SUM|AVG|MIN|MAX aggregate values for $field.
     *
     * @return $this
     */
    public function aggregate(string $fx, string $field, bool $coalesce = false)
    {
        $result = 0;
        $column = array_column($this->getRows(), $field);

        switch (strtoupper($fx)) {
            case 'SUM':
                $result = array_sum($column);

                break;
            case 'AVG':
                if (!$coalesce) { // TODO add tests and verify against SQL
                    $column = array_filter($column, static fn ($v) => $v !== null);
                }

                $result = array_sum($column) / count($column);

                break;
            case 'MAX':
                $result = max($column);

                break;
            case 'MIN':
                $result = min($column);

                break;
            default:
                throw (new Exception('Array persistence driver action unsupported format'))
                    ->addMoreInfo('action', $fx);
        }

        $this->generator = new \ArrayIterator([['v' => $result]]);

        return $this;
    }

    /**
     * Checks if $row matches $condition.
     *
     * @param array<string, mixed> $row
     */
    protected function match(array $row, Model\Scope\AbstractScope $condition): bool
    {
        if ($condition instanceof Model\Scope\Condition) { // simple condition
            $args = $condition->toQueryArguments();

            $field = $args[0];
            $operator = $args[1] ?? null;
            $value = $args[2] ?? null;
            if (count($args) === 2) {
                $value = $operator;

                $operator = '=';
            }

            if (!is_a($field, Field::class)) {
                throw (new Exception('Array persistence driver condition unsupported format'))
                    ->addMoreInfo('reason', 'Unsupported object instance ' . get_class($field))
                    ->addMoreInfo('condition', $condition);
            }

            return $this->evaluateIf($row[$field->shortName] ?? null, $operator, $value);
        } elseif ($condition instanceof Model\Scope) { // nested conditions
            $matches = [];
            foreach ($condition->getNestedConditions() as $nestedCondition) {
                $matches[] = $subMatch = $this->match($row, $nestedCondition);

                // do not check all conditions if any match required
                if ($condition->isOr() && $subMatch) {
                    break;
                }
            }

            // any matches && all matches the same (if all required)
            return array_filter($matches) && ($condition->isAnd() ? count(array_unique($matches)) === 1 : true);
        }

        throw (new Exception('Unexpected condition type'))
            ->addMoreInfo('class', get_class($condition));
    }

    /**
     * @param mixed $v1
     * @param mixed $v2
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

        switch (strtoupper($operator)) {
            case '=':
                $result = is_array($v2) ? $this->evaluateIf($v1, 'IN', $v2) : $v1 === $v2;

                break;
            case '>':
                $result = $v1 > $v2;

                break;
            case '>=':
                $result = $v1 >= $v2;

                break;
            case '<':
                $result = $v1 < $v2;

                break;
            case '<=':
                $result = $v1 <= $v2;

                break;
            case '!=':
                $result = !$this->evaluateIf($v1, '=', $v2);

                break;
            case 'LIKE':
                $pattern = str_ireplace('%', '(.*?)', preg_quote($v2, '~'));

                $result = (bool) preg_match('~^' . $pattern . '$~', (string) $v1);

                break;
            case 'NOT LIKE':
                $result = !$this->evaluateIf($v1, 'LIKE', $v2);

                break;
            case 'IN':
                $result = false;
                foreach ($v2 as $v2Item) { // TODO flatten rows, this looses column names!
                    if ($this->evaluateIf($v1, '=', $v2Item)) {
                        $result = true;

                        break;
                    }
                }

                break;
            case 'NOT IN':
                $result = !$this->evaluateIf($v1, 'IN', $v2);

                break;
            case 'REGEXP':
                $result = (bool) preg_match('/' . $v2 . '/', $v1);

                break;
            case 'NOT REGEXP':
                $result = !$this->evaluateIf($v1, 'REGEXP', $v2);

                break;
            default:
                throw (new Exception('Unsupported operator'))
                    ->addMoreInfo('operator', $operator);
        }

        return $result;
    }

    /**
     * Applies sorting on Iterator.
     *
     * @param array<int, array{string, 'asc'|'desc'}> $fields
     *
     * @return $this
     */
    public function order(array $fields)
    {
        $data = $this->getRows();

        // prepare arguments for array_multisort()
        $args = [];
        foreach ($fields as [$field, $direction]) {
            $args[] = array_column($data, $field);
            $args[] = strtolower($direction) === 'desc' ? \SORT_DESC : \SORT_ASC;
        }
        $args[] = &$data;

        // call sorting
        array_multisort(...$args);

        // put data back in generator
        $this->generator = new \ArrayIterator(array_pop($args));

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
        $this->generator = new \ArrayIterator([['v' => iterator_count($this->generator)]]);

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
        $this->generator = new \ArrayIterator([['v' => $this->generator->valid() ? 1 : 0]]);

        return $this;
    }

    /**
     * Return all data inside array.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getRows(): array
    {
        return iterator_to_array($this->generator, true);
    }

    /**
     * Return one row of data.
     *
     * @return array<string, mixed>|null
     */
    public function getRow(): ?array
    {
        $this->generator->rewind(); // TODO alternatively allow to fetch only once
        $row = $this->generator->current();
        $this->generator->next();

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

        return reset($data);
    }
}
