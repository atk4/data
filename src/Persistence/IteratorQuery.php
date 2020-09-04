<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Model\Scope\Condition;
use atk4\data\Persistence;

/**
 * Class to perform queries on Array_ persistence.
 */
class IteratorQuery extends AbstractQuery
{
    /** @var \Iterator */
    protected $iterator;

    protected $fields;

    /**
     * Closure to be executed when calling IteratorQuery::execute.
     *
     * @var \Closure
     * */
    protected $fx;

    public function __construct(Model $model, Persistence $persistence = null)
    {
        parent::__construct($model, $persistence);

        $this->iterator = $this->persistence->getRawDataIterator($model->table);

        $this->fx = function (\Iterator $iterator) {
            return $iterator;
        };
    }

    protected function initSelect($fields = null): void
    {
        if ($fields) {
            $this->fields = $fields;

            $this->fx = function (\Iterator $iterator) {
                $keys = array_flip((array) $this->fields);

                return new \atk4\data\Util\ArrayCallbackIterator($iterator, function ($row) use ($keys) {
                    return array_intersect_key($row, $keys);
                });
            };
        }
    }

    protected function initInsert(array $data): void
    {
        $this->fx = function (\Iterator $iterator) use ($data) {
            return $this->persistence->setRawData($this->model, $data);
        };
    }

    protected function initUpdate(array $data): void
    {
        $this->fx = function (\Iterator $iterator) use ($data) {
            foreach ($iterator as $id => $row) {
                $this->persistence->setRawData($this->model, array_merge($row, $data), $id);
            }
        };
    }

    protected function initDelete(): void
    {
        $this->fx = function (\Iterator $iterator) {
            foreach ($iterator as $id => $row) {
                $this->persistence->unsetRawData($this->model->table, $id);
            }
        };
    }

    /**
     * Applies sorting on Iterator.
     *
     * @param array $fields
     *
     * @return $this
     */
    protected function initOrder(): void
    {
        if ($this->order) {
            $data = $this->doGet();

            // prepare arguments for array_multisort()
            $args = [];
            foreach ($this->order as [$field, $order]) {
                $args[] = array_column($data, $field);
                $args[] = $order === 'desc' ? SORT_DESC : SORT_ASC;
            }
            $args[] = &$data;

            // call sorting
            array_multisort(...$args);

            // put data back in generator
            $this->iterator = new \ArrayIterator(array_pop($args));
        }
    }

    protected function initLimit(): void
    {
        if ($args = $this->getLimitArgs()) {
            [$limit, $offset] = $args;

            $this->iterator = new \LimitIterator($this->iterator, $offset, $limit);
        }
    }

    /**
     * Counts number of rows and replaces our generator with just a single number.
     *
     * @return $this
     */
    protected function initCount($alias = null): void
    {
        // @todo: kept for BC, inconstent results with SQL count!
        $this->initLimit();

        $alias = $alias ?? 'count';

        $this->iterator = new \ArrayIterator([[$alias => iterator_count($this->iterator)]]);
    }

    /**
     * Checks if iterator has any rows.
     *
     * @return $this
     */
    protected function initExists(): void
    {
        $this->iterator = new \ArrayIterator([[$this->iterator->valid() ? 1 : 0]]);
    }

    protected function initField($fieldName, string $alias = null): void
    {
        if (!$fieldName) {
            throw new Exception('Field query requires field name');
        }

        // get first record
        if ($row = $this->persistence->query($this->model)->select([$fieldName])->getRow()) {
            if ($alias && array_key_exists($fieldName, $row)) {
                $row[$alias] = $row[$fieldName];
                unset($row[$fieldName]);
            }
        }

        $this->iterator = new \ArrayIterator([[$row]]);
    }

    protected function doExecute()
    {
        return ($this->fx)($this->iterator);
    }

    /**
     * Return all data inside array.
     */
    protected function doGet(): array
    {
        return iterator_to_array($this->doExecute(), true);
    }

    /**
     * Return one row of data.
     */
    protected function doGetRow(): ?array
    {
        $iterator = $this->doExecute();

        $iterator->rewind();

        return $this->getRowWithId($iterator->current(), $this->iterator->key());
    }

    protected function getRowWithId($row, $id)
    {
        if ($row && $this->model->id_field && !isset($row[$this->model->id_field])) {
            $field = $this->model->getField($this->model->id_field);

            // typecastSave value so we can use strict comparison
            $row[$this->model->id_field] = $this->persistence->typecastSaveField($field, $id);
        }

        return $row;
    }

    /**
     * Return one value from one row of data.
     *
     * @return mixed
     */
    protected function doGetOne()
    {
        $data = $this->getRow();

        return reset($data);
    }

    /**
     * Calculates SUM|AVG|MIN|MAX aggragate values for $field.
     *
     * @param string $fieldName
     * @param string $alias
     */
    protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void
    {
        $field = is_string($field) ? $field : $field->short_name;

        $result = 0;
        $column = array_column($this->get(), $field);

        switch (strtoupper($functionName)) {
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
                throw (new Exception('Persistence\Array_ query unsupported aggregate function'))
                    ->addMoreInfo('function', $functionName);
        }

        $this->iterator = new \ArrayIterator([[$result]]);
    }

    /**
     * Applies FilterIterator.
     */
    protected function initWhere(): void
    {
        if (!$this->scope->isEmpty()) {
            $this->iterator = new \CallbackFilterIterator($this->iterator, function ($row, $id) {
                return $this->match($this->getRowWithId($row, $id), $this->scope);
            });
        }
    }

    /**
     * Checks if $row matches $condition.
     */
    protected function match(array $row, Model\Scope\AbstractScope $condition): bool
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

                $operator = Condition::OPERATOR_EQUALS;
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
            case Condition::OPERATOR_EQUALS:
                $result = is_array($v2) ? $this->evaluateIf($v1, Condition::OPERATOR_IN, $v2) : $v1 === $v2;

            break;
            case Condition::OPERATOR_GREATER:
                $result = $v1 > $v2;

            break;
            case Condition::OPERATOR_GREATER_EQUAL:
                $result = $v1 >= $v2;

            break;
            case Condition::OPERATOR_LESS:
                $result = $v1 < $v2;

            break;
            case Condition::OPERATOR_LESS_EQUAL:
                $result = $v1 <= $v2;

            break;
            case Condition::OPERATOR_DOESNOT_EQUAL:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_EQUALS, $v2);

            break;
            case Condition::OPERATOR_LIKE:
                $pattern = str_ireplace('%', '(.*?)', preg_quote($v2));

                $result = (bool) preg_match('/^' . $pattern . '$/', (string) $v1);

            break;
            case Condition::OPERATOR_NOT_LIKE:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_LIKE, $v2);

            break;
            case Condition::OPERATOR_IN:
                $result = is_array($v2) ? in_array($v1, $v2, true) : $this->evaluateIf($v1, '=', $v2);

            break;
            case Condition::OPERATOR_NOT_IN:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_IN, $v2);

            break;
            case Condition::OPERATOR_REGEXP:
                $result = (bool) preg_match('/' . $v2 . '/', $v1);

            break;
            case Condition::OPERATOR_NOT_REGEXP:
                $result = !$this->evaluateIf($v1, Condition::OPERATOR_REGEXP, $v2);

            break;
            default:
                throw (new Exception('Unsupported operator'))
                    ->addMoreInfo('operator', $operator);
        }

        return $result;
    }

    public function getDebug(): array
    {
        return array_merge([
            'fields' => $this->fields,
        ], parent::getDebug());
    }
}
