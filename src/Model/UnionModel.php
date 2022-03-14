<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\Exception as CoreException;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;

/**
 * UnionModel model combines multiple nested models through a UNION in order to retrieve
 * it's value set. The beauty of this class is that it will add fields transparently
 * and will map them appropriately from the nested model if you request
 * those fields from the union model.
 *
 * For example if you are asking sum(amount), there is no need to fetch any extra
 * fields from sub-models.
 *
 * @property \Atk4\Data\Persistence\Sql $persistence
 *
 * @method Expression expr($expr, array $args = []) forwards to Persistence\Sql::expr using $this as model
 */
class UnionModel extends Model
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';

    /**
     * UnionModel should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * UnionModel normally does not have ID field. Setting this to null will
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
     * $union = [
     *     [$model1, ['amount' => 'total_gross'] ],
     *     [$model2, []]
     * ];
     *
     * @var array
     */
    public $union = [];

    /** @var string Derived table alias */
    public $table = '_tu';

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
     * Adds nested model in union.
     */
    public function addNestedModel(Model $model, array $fieldMap = []): Model
    {
        $this->persistence->add($model);

        $this->union[] = [$model, $fieldMap];

        return $model; // TODO nothing/void should be returned
    }

    /**
     * If UnionModel model has such field, then add condition to it.
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

        // if UnionModel has such field, then add condition to it
        if ($this->hasField($key) && !$forceNested) {
            return parent::addCondition(...func_get_args());
        }

        // otherwise add condition in all nested models
        foreach ($this->union as [$nestedModel, $fieldMap]) {
            try {
                $field = $key;

                if (isset($fieldMap[$key])) {
                    // field is included in mapping - use mapping expression
                    $field = $fieldMap[$key] instanceof Expression
                        ? $fieldMap[$key]
                        : $this->getFieldExpr($nestedModel, $key, $fieldMap[$key]);
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
                    default:
                        $nestedModel->addCondition($field, $operator, $value);

                        break;
                }
            } catch (CoreException $e) {
                throw $e
                    ->addMoreInfo('nestedModel', $nestedModel);
            }
        }

        return $this;
    }

    /**
     * @return Query
     */
    public function action(string $mode, array $args = [])
    {
        $subquery = null;
        switch ($mode) {
            case 'select':
                // get list of available fields
                $fields = $this->onlyFields ?: array_keys($this->getFields());
                foreach ($fields as $k => $field) {
                    if ($this->getField($field)->never_persist) {
                        unset($fields[$k]);
                    }
                }
                $subquery = $this->getSubQuery($fields);
                $query = parent::action($mode, $args)->reset('table')->table($subquery, $this->table_alias ?? $this->table);

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
            case 'fx0':
                $args['alias'] = 'val';

                $subquery = $this->getSubAction($mode, $args);

                $args = [$args[0], $this->expr('{}', ['val'])];

                break;
            default:
                throw (new Exception('UnionModel model does not support this action'))
                    ->addMoreInfo('mode', $mode);
        }

        // Substitute FROM table with our subquery expression
        return parent::action($mode, $args)->reset('table')->table($subquery, $this->table_alias ?? $this->table);
    }

    /**
     * @param Query[] $subqueries
     */
    private function createUnionQuery(array $subqueries): Query
    {
        $unionQuery = $this->persistence->dsql();
        $unionQuery->mode = 'union_all';
        \Closure::bind(function () use ($unionQuery, $subqueries) {
            $unionQuery->template = implode(' UNION ALL ', array_fill(0, count($subqueries), '[]'));
        }, null, Query::class)();
        $unionQuery->args['custom'] = $subqueries;

        return $unionQuery;
    }

    /**
     * Configures nested models to have a specified set of fields available.
     */
    public function getSubQuery(array $fields): Query
    {
        $subqueries = [];

        foreach ($this->union as [$nestedModel, $fieldMap]) {
            // map fields for related model
            $queryFieldExpressions = [];
            foreach ($fields as $fieldName) {
                try {
                    // UnionModel can be joined with additional table/query
                    // We don't touch those fields

                    if (!$this->hasField($fieldName)) {
                        $queryFieldExpressions[$fieldName] = $nestedModel->expr('NULL');

                        continue;
                    }

                    $field = $this->getField($fieldName);

                    if ($field->hasJoin() || $field->never_persist) {
                        continue;
                    }

                    // UnionModel can have some fields defined as expressions. We don't touch those either.
                    // Imants: I have no idea why this condition was set, but it's limiting our ability
                    // to use expression fields in mapping
                    if ($field instanceof SqlExpressionField /* && !isset($this->aggregate[$fieldName]) */) {
                        continue;
                    }

                    $fieldExpression = $this->getFieldExpr($nestedModel, $fieldName, $fieldMap[$fieldName] ?? null);

                    $queryFieldExpressions[$fieldName] = $fieldExpression;
                } catch (CoreException $e) {
                    throw $e
                        ->addMoreInfo('nestedModel', $nestedModel);
                }
            }

            // now prepare query
            $query = $this->persistence->action($nestedModel, 'select', [false]);

            foreach ($queryFieldExpressions as $fAlias => $fExpr) {
                $query->field($fExpr, $fAlias);
            }

            // subquery should not be wrapped in parenthesis, SQLite is especially picky about that
            $query->wrapInParentheses = false;

            $subqueries[] = $query;
        }

        $unionQuery = $this->createUnionQuery($subqueries);

        return $unionQuery;
    }

    public function getSubAction(string $action, array $actionArgs = []): Query
    {
        $subqueries = [];

        foreach ($this->union as [$model, $fieldMap]) {
            $modelActionArgs = $actionArgs;

            // now prepare query
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

            $subqueries[] = $query;
        }

        $unionQuery = $this->createUnionQuery($subqueries);

        return $unionQuery;
    }

    public function __debugInfo(): array
    {
        return array_merge(parent::__debugInfo(), [
            'unionModels' => $this->union,
        ]);
    }
}
