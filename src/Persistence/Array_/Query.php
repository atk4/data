<?php

declare(strict_types=1);

namespace atk4\data\Persistence\Array_;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\Persistence\QueryInterface;

/**
 * Class Array_ is returned by $model->toQuery(). Compatible with DSQL to a certain point as it implements
 * specific actions such as getOne() or get().
 */
class Query implements Persistence\QueryInterface
{
    /**
     * @var Model
     */
    protected $model;

    protected $scope;

    protected $order;

    protected $limit;

    private $iterator;

    private $data;

    public function __construct(Model $model)
    {
        $this->data = $model->persistence->getRawDataByTable($model->table);

        $this->iterator = new \ArrayIterator($this->data);

        $this->scope = clone $model->scope();

        $this->order = $model->order;

        $this->limit = $model->limit;
    }

    public function find($id): ?array
    {
        $query = $this->model->toQuery('select');

        $query->scope->addCondition($this->model->id_field, $id);

        return $query->getRow();
//         $this->scope->addCondition($this->model->id_field, $id);

//         return $this->select()->limit(1)->getRow();
    }

    public function select($fields = []): QueryInterface
    {
        $this->initFields($fields);
        $this->initWhere();
        $this->initOrder();
        $this->initLimit();

        return $this;
    }

    protected function initFields($fields = null)
    {
        if ($fields) {
            $data = $this->get();

            $keys = array_flip((array) $fields);

            $data = array_map(function ($row) use ($keys) {
                return array_intersect_key($row, $keys);
            }, $this->get());

            $this->iterator = new \ArrayIterator($data);
        }

        return $this;
    }

    public function update()
    {
    }

    public function insert()
    {
    }

    public function delete(): QueryInterface
    {
    }

    public function getIterator()
    {
        return $this->iterator;
    }

    public function order($field, $desc = null): QueryInterface
    {
//         $this->iterator->uasort(function($a, $b) use ($field, $desc) {
//             if ($a[$field] == $b[$field]) {
//                 return 0;
//             }

//             $order = $desc ? -1 : 1;

//             return $order * (($a[$field] < $b[$field]) ? -1 : 1);
//         });

//         return $this;
    }

    /**
     * Applies sorting on Iterator.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function initOrder()
    {
        if (!$this->order) {
            return;
        }
//         foreach ($fields as [$field, $desc]) {
//             $this->order($field, $desc);
//         }

//         return $this;
        $data = $this->get();

        // prepare arguments for array_multisort()
        $args = [];
        foreach ($this->order as [$field, $desc]) {
            $args[] = array_column($data, $field);
            $args[] = $desc ? SORT_DESC : SORT_ASC;
        }
        $args[] = &$data;

        // call sorting
        array_multisort(...$args);

        // put data back in generator
        $this->iterator = new \ArrayIterator(array_pop($args));

        return $this;
    }

    /**
     * Limit Iterator.
     *
     * @param int $limit
     * @param int $offset
     *
     * @return $this
     */
    public function limit($limit, $offset = 0): QueryInterface
    {
        // put data back in generator
        $this->iterator = new \LimitIterator($this->iterator, $offset, $limit);

        return $this;
    }

    protected function initLimit()
    {
        if ($this->limit) {
            $offset = $this->limit[1] ?? 0;
            $limit = $this->limit[0] ?? null;

            if ($limit || $offset) {
                if ($limit === null) {
                    $limit = PHP_INT_MAX;
                }

                $this->limit($limit, $offset ?? 0);
            }
        }

        return $this;
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     *
     * @return $this
     */
    public function count($alias = null): QueryInterface
    {
        $this->initWhere();
        // @todo: kept for BC, inconstent results with SQL count!
        $this->initLimit();

        $alias = $alias ?? 'count';

        $this->iterator = new \ArrayIterator([[$alias => iterator_count($this->iterator)]]);

        return $this;
    }

    /**
     * Checks if iterator has any rows.
     *
     * @return $this
     */
    public function exists(): QueryInterface
    {
        $this->iterator = new \ArrayIterator([[$this->iterator->valid() ? 1 : 0]]);

        return $this;
    }

//     public function where($fieldName, $operator = null, $value = null): QueryInterface
//     {
//         $this->scope->addCondition(...func_get_args());

//         return $this;
//     }

    public function field($fieldName, string $alias = null): QueryInterface
    {
        if (!$fieldName) {
            throw new Exception('Field query requires field name');
        }

        $field = $fieldName;
        if (!is_string($fieldName)) {
            $field = $this->model->getField($fieldName);
            $fieldName = $field->short_name;
        }

        $this->initFields([$fieldName]);
        $this->initWhere();
        $this->initOrder();
        $this->initLimit();

        // get first record
        if ($row = $this->getRow()) {
            if ($alias && array_key_exists($field, $row)) {
                $row[$alias] = $row[$field];
                unset($row[$field]);
            }
        }

        $this->iterator = new \ArrayIterator([[$row]]);

        return $this;
    }

    /**
     * Return all data inside array.
     */
    public function get(): array
    {
        return iterator_to_array($this->iterator, true);
    }

    /**
     * Return one row of data.
     */
    public function getRow(): ?array
    {
        $row = $this->iterator->current();

        $this->iterator->next();

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

    /**
     * Calculates SUM|AVG|MIN|MAX aggragate values for $field.
     *
     * @param string $fx
     * @param string $field
     * @param bool   $coalesce
     *
     * @return \atk4\data\Action\Iterator
     */
    public function aggregate($fx, $field, $alias = null, $coalesce = false)
    {
        $this->initWhere();
        $this->initLimit();

        $result = 0;
        $column = array_column($this->get(), $field);

        switch (strtoupper($fx)) {
            case 'SUM':
                $result = array_sum($column);

            break;
            case 'AVG':
                $column = $coalesce ? $column : array_filter($column, function ($value) {
                    return $value !== null;
                });

                $result = array_sum($column) / count($column);

            break;
            case 'MAX':
                $result = max($column);

            break;
            case 'MIN':
                $result = min($column);

            break;
            default:
                throw (new Exception('Persistence\Array_ driver action unsupported format'))
                    ->addMoreInfo('action', $fx);
        }

        $this->iterator = new \ArrayIterator([[$result]]);

        return $this;
    }

    /**
     * Applies FilterIterator making sure that values of $field equal to $value.
     *
     * @param string $field
     * @param string $value
     *
     * @return $this
     */
    protected function initWhere()
    {
        if (!$this->scope->isEmpty()) {
            $this->iterator = new \CallbackFilterIterator($this->iterator, function ($row, $id) {
                // make sure we use the complete row with the filter
                $row = $this->data[$id];

                return $this->match($row, $this->scope);
            });
        }

        return $this;
    }

    /**
     * Checks if $row matches $condition.
     *
     * @return bool
     */
    protected function match(array $row, Model\Scope\AbstractScope $condition)
    {
        $match = false;

        // simple condition
        if ($condition instanceof Model\Scope\Condition) {
            $args = $condition->toQueryArguments();

            $field = $args[0];
            $operator = $args[1] ?? null;
            $value = $args[2] ?? null;
            if (count($args) == 2) {
                $value = $operator;

                $operator = '=';
            }

            if (!is_a($field, Field::class)) {
                throw (new Exception('Persistence\Array_ driver condition unsupported format'))
                    ->addMoreInfo('reason', 'Unsupported object instance ' . get_class($field))
                    ->addMoreInfo('condition', $condition);
            }

            $match = $this->evaluateIf($row[$field->short_name] ?? null, $operator, $value);
        }

        // nested conditions
        if ($condition instanceof Model\Scope) {
            $matches = [];

            foreach ($condition->getNestedConditions() as $nestedCondition) {
                $matches[] = $subMatch = (bool) $this->match($row, $nestedCondition);

                // do not check all conditions if any match required
                if ($condition->isOr() && $subMatch) {
                    break;
                }
            }

            // any matches && all matches the same (if all required)
            $match = array_filter($matches) && ($condition->isAnd() ? count(array_unique($matches)) === 1 : true);
        }

        return $match;
    }

    protected function evaluateIf($v1, $operator, $v2): bool
    {
        switch (strtoupper((string) $operator)) {
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
            case '<>':
                $result = !$this->evaluateIf($v1, '=', $v2);

            break;
            case 'LIKE':
                $pattern = str_ireplace('%', '(.*?)', preg_quote($v2));

                $result = (bool) preg_match('/^' . $pattern . '$/', (string) $v1);

            break;
            case 'NOT LIKE':
                $result = !$this->evaluateIf($v1, 'LIKE', $v2);

            break;
            case 'IN':
                $result = is_array($v2) ? in_array($v1, $v2, true) : $this->evaluateIf($v1, '=', $v2);

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
}
