<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\data\Model;
use atk4\dsql\Expression;
use atk4\dsql\Query;

/**
 * Union model combines multiple nested models through a UNION in order to retrieve
 * it's value set. The beauty of this class is that it will add fields transparently
 * and will map them appropriately from the nested model if you request
 * those fields from the union model.
 *
 * For example if you are asking sum(amount), there is no need to fetch any extra
 * fields from sub-models.
 *
 * @property \atk4\data\Persistence\Sql $persistence
 *
 * @method Expression expr($expr, array $args = []) forwards to Persistence\Sql::expr using $this as model
 */
class Union extends Model
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';

    /** @deprecated use HOOK_INIT_SELECT_QUERY instead - will be removed dec-2020 */
    public const HOOK_AFTER_UNION_SELECT = self::HOOK_INIT_SELECT_QUERY;

    /**
     * Union model should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * Union normally does not have ID field. Setting this to null will
     * disable various per-id operations, such as load().
     *
     * If you can define unique ID field, you can specify it inside your
     * union model.
     *
     * @var string
     */
    public $id_field;

    /**
     * Contain array of array containing model and mappings.
     *
     * $union = [ [ $model1, ['amount'=>'total_gross'] ] , [$model2, []] ];
     *
     * @var array
     */
    public $union = [];

    /**
     * When aggregation happens, this field will contain list of fields
     * we use in groupBy. Multiple fields can be in the array. All
     * the remaining fields will be hidden (marked as system()) and
     * have their "aggregates" added into the selectQuery (if possible).
     *
     * @var array|string
     */
    public $group;

    /**
     * When grouping, the functions will be applied as per aggregate
     * fields, e.g. 'balance'=>['sum', 'amount'].
     *
     * You can also use Expression instead of array.
     *
     * @var array
     */
    public $aggregate = [];

    /** @var string Derived table alias */
    public $table = 'derivedTable';

    /**
     * For a sub-model with a specified mapping, return expression
     * that represents a field.
     *
     * @return Field|Expression
     */
    public function getFieldExpr(Model $model, string $fieldName, string $expr = null)
    {
        if ($model->hasField($fieldName)) {
            $field = $model->getField($fieldName);
        } else {
            $field = $this->expr('NULL');
        }

        // Some fields are re-mapped for this nested model
        if ($expr !== null) {
            $field = $model->expr($expr, [$field]);
        }

        return $field;
    }

    /**
     * Configures nested models to have a specified set of fields
     * available.
     */
    public function getSubQuery(array $fields): Expression
    {
        $cnt = 0;
        $expr = [];
        $args = [];

        foreach ($this->union as $n => [$nestedModel, $fieldMap]) {
            // map fields for related model
            $queryFieldExpressions = [];
            foreach ($fields as $fieldName) {
                try {
                    // Union can be joined with additional
                    // table/query and we don't touch those
                    // fields

                    if (!$this->hasField($fieldName)) {
                        $queryFieldExpressions[$fieldName] = $nestedModel->expr('NULL');

                        continue;
                    }

                    $field = $this->getField($fieldName);

                    if ($field->hasJoin() || $field->never_persist) {
                        continue;
                    }

                    // Union can have some fields defined as expressions. We don't touch those either.
                    // Imants: I have no idea why this condition was set, but it's limiting our ability
                    // to use expression fields in mapping
                    if ($field instanceof FieldSqlExpression && !isset($this->aggregate[$fieldName])) {
                        continue;
                    }

                    // if we group we do not select non-aggregate fields
                    if ($this->group && !in_array($fieldName, (array) $this->group, true) && !isset($this->aggregate[$fieldName])) {
                        continue;
                    }

                    $fieldExpression = $this->getFieldExpr($nestedModel, $fieldName, $fieldMap[$fieldName] ?? null);

                    if (isset($this->aggregate[$fieldName])) {
                        $seed = (array) $this->aggregate[$fieldName];

                        // first element of seed should be expression itself
                        $fieldExpression = $nestedModel->expr($seed[0], [$fieldExpression]);
                    }

                    $queryFieldExpressions[$fieldName] = $fieldExpression;
                } catch (\atk4\core\Exception $e) {
                    throw $e->addMoreInfo('model', $n);
                }
            }

            // now prepare query
            $expr[] = '[' . $cnt . ']';
            $query = $this->persistence->action($nestedModel, 'select', [false]);

            if ($nestedModel instanceof self) {
                $subquery = $nestedModel->getSubQuery($fields);
                //$query = parent::action($mode, $args);
                $query->reset('table')->table($subquery);

                if (isset($nestedModel->group)) {
                    $query->group($nestedModel->group);
                }
            }

            $query->field($queryFieldExpressions);

            // also for sub-queries
            if ($this->group) {
                if (is_array($this->group)) {
                    foreach ($this->group as $group) {
                        if (isset($fieldMap[$group])) {
                            $query->group($nestedModel->expr($fieldMap[$group]));
                        } elseif ($nestedModel->hasField($group)) {
                            $query->group($nestedModel->getField($group));
                        }
                    }
                } elseif (isset($fieldMap[$this->group])) {
                    $query->group($nestedModel->expr($fieldMap[$this->group]));
                } else {
                    $query->group($this->group);
                }
            }

            // subquery should not be wrapped in parenthesis, SQLite is especially picky about that
            $query->wrapInParentheses = false;

            $args[$cnt++] = $query;
        }

        // last element is table name itself
        $args[$cnt] = $this->table;

        return $this->persistence->dsql()->expr('(' . implode(' UNION ALL ', $expr) . ') {' . $cnt . '}', $args);
    }

    public function getSubAction(string $action, array $actionArgs = []): Expression
    {
        $cnt = 0;
        $expr = [];
        $exprArgs = [];

        foreach ($this->union as [$model, $fieldMap]) {
            $modelActionArgs = $actionArgs;

            // now prepare query
            $expr[] = '[' . $cnt . ']';
            if ($fieldName = $actionArgs[1] ?? null) {
                $modelActionArgs[1] = $this->getFieldExpr(
                    $model,
                    $fieldName,
                    $fieldMap[$fieldName] ?? null
                );
            }

            $query = $model->action($action, $modelActionArgs);

            // subquery should not be wrapped in parenthesis, SQLite is especially picky about that
            $query->wrapInParentheses = false;

            $exprArgs[$cnt++] = $query;
        }

        $expr = '(' . implode(' UNION ALL ', $expr) . ') {' . $cnt . '}';
        // last element is table name itself
        $exprArgs[$cnt] = $this->table;

        return $this->persistence->dsql()->expr($expr, $exprArgs);
    }

    /**
     * Execute action.
     *
     * @param string $mode
     * @param array  $args
     *
     * @return Query
     */
    public function action($mode, $args = [])
    {
        $subquery = null;
        switch ($mode) {
            case 'select':
                // get list of available fields
                $fields = $this->only_fields ?: array_keys($this->getFields());
                foreach ($fields as $k => $field) {
                    if ($this->getField($field)->never_persist) {
                        unset($fields[$k]);
                    }
                }
                $subquery = $this->getSubQuery($fields);
                $query = parent::action($mode, $args)->reset('table')->table($subquery);

                if (isset($this->group)) {
                    $query->group($this->group);
                }
                $this->hook(self::HOOK_INIT_SELECT_QUERY, [$query]);

                return $query;
            case 'count':
                $subquery = $this->getSubAction('count', ['alias' => 'cnt']);

                $mode = 'fx';
                $args = ['sum', $this->expr('{}', ['cnt'])];

                break;
            case 'field':
                if (!isset($args[0])) {
                    throw (new Exception('This action requires one argument with field name'))
                        ->addMoreInfo('mode', $mode);
                }

                if (!is_string($args[0])) {
                    throw (new Exception('action "field" only support string fields'))
                        ->addMoreInfo('field', $args[0]);
                }

                $subquery = $this->getSubQuery([$args[0]]);

                break;
            case 'fx':
                $args['alias'] = 'val';

                $subquery = $this->getSubAction('fx', $args);

                $args = [$args[0], $this->expr('{}', ['val'])];

                break;
            default:
                throw (new Exception('Union model does not support this action'))
                    ->addMoreInfo('mode', $mode);
        }

        // Substitute FROM table with our subquery expression
        return parent::action($mode, $args)->reset('table')->table($subquery);
    }

    /**
     * Adds nested model in union.
     */
    public function addNestedModel(Model $model, array $fieldMap = []): Model
    {
        $nestedModel = $this->persistence->add($model);

        $this->union[] = [$nestedModel, $fieldMap];

        return $nestedModel;
    }

    /**
     * Specify a single field or array of fields.
     *
     * @param string|array $group
     */
    public function groupBy($group, array $aggregate = []): Model
    {
        $this->aggregate = $aggregate;
        $this->group = $group;

        foreach ($aggregate as $fieldName => $seed) {
            $seed = (array) $seed;

            $field = $this->hasField($fieldName) ? $this->getField($fieldName) : null;

            // first element of seed should be expression itself
            if (isset($seed[0]) && is_string($seed[0])) {
                $seed[0] = $this->expr($seed[0], $field ? [$field] : null);
            }

            if ($field) {
                $this->removeField($fieldName);
            }

            $this->addExpression($fieldName, $seed);
        }

        foreach ($this->union as [$nestedModel, $fieldMap]) {
            if ($nestedModel instanceof self) {
                $nestedModel->aggregate = $aggregate;
                $nestedModel->group = $group;
            }
        }

        return $this;
    }

    /**
     * Adds condition.
     *
     * If Union model has such field, then add condition to it.
     * Otherwise adds condition to all nested models.
     *
     * @param mixed $key
     * @param mixed $operator
     * @param mixed $value
     * @param bool  $forceNested Should we add condition to all nested models?
     *
     * @return $this
     */
    public function addCondition($key, $operator = null, $value = null, $forceNested = false)
    {
        if (func_num_args() === 1) {
            return parent::addCondition($key);
        }

        // if Union model has such field, then add condition to it
        if ($this->hasField($key) && !$forceNested) {
            return parent::addCondition(...func_get_args());
        }

        // otherwise add condition in all sub-models
        foreach ($this->union as $n => [$nestedModel, $fieldMap]) {
            try {
                $field = $key;

                if (isset($fieldMap[$key])) {
                    // field is included in mapping - use mapping expression
                    $field = $fieldMap[$key] instanceof Expression
                            ? $fieldMap[$key]
                            : $this->expr($fieldMap[$key], $nestedModel->getFields());
                } elseif (is_string($key) && $nestedModel->hasField($key)) {
                    // model has such field - use that field directly
                    $field = $nestedModel->getField($key);
                } else {
                    // we don't know what to do, so let's do nothing
                    continue;
                }

                switch (func_num_args()) {
                    case 2:
                        $nestedModel->addCondition($field, $operator);

                        break;
                    case 3:
                    case 4:
                        $nestedModel->addCondition($field, $operator, $value);

                        break;
                }
            } catch (\atk4\core\Exception $e) {
                throw $e->addMoreInfo('sub_model', $n);
            }
        }

        return $this;
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $unionModels = [];
        foreach ($this->union as [$nestedModel, $fieldMap]) {
            $unionModels[get_class($nestedModel)] = array_merge(
                ['fieldMap' => $fieldMap],
                $nestedModel->__debugInfo()
            );
        }

        return array_merge(
            parent::__debugInfo(),
            [
                'group' => $this->group,
                'aggregate' => $this->aggregate,
                'unionModels' => $unionModels,
            ]
        );
    }

    // }}}
}
