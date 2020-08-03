<?php

declare(strict_types=1);

namespace atk4\data\Persistence\Sql;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\data\Model;
use atk4\data\Persistence\AbstractQuery;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;
use atk4\dsql\Query as DsqlQuery;

/**
 * @method Query getDebugQuery()
 * @method Query render()
 * @method Query mode()
 */
class Query extends AbstractQuery implements Expressionable
{
    /** @var DsqlQuery */
    protected $dsql;

    public function __construct(Model $model)
    {
        parent::__construct($model);

        $this->dsql = $model->persistence_data['dsql'] = $model->persistence->dsql();

        if ($model->table) {
            $this->dsql->table($model->table, $model->table_alias ?? null);
        }

        // add With cursors
        $this->addWithCursors();
    }

    protected function addWithCursors()
    {
        if (!$with = $this->model->with) {
            return;
        }

        foreach ($with as $alias => ['model' => $withModel, 'mapping' => $withMapping, 'recursive' => $recursive]) {
            // prepare field names
            $fieldsFrom = $fieldsTo = [];
            foreach ($withMapping as $from => $to) {
                $fieldsFrom[] = is_int($from) ? $to : $from;
                $fieldsTo[] = $to;
            }

            // prepare sub-query
            if ($fieldsFrom) {
                $withModel->onlyFields($fieldsFrom);
            }
            // 2nd parameter here strictly define which fields should be selected
            // as result system fields will not be added if they are not requested
            $subQuery = $withModel->toQuery('select', [$fieldsFrom])->dsql();

            // add With cursor
            $this->dsql->with($subQuery, $alias, $fieldsTo ?: null, $recursive);
        }
    }

    public function find($id): ?array
    {
        if (!$this->model->id_field) {
            throw (new Exception('Unable to load record by "id" when Model::id_field is not defined.'))
                ->addMoreInfo('id', $id);
        }

        //to trigger init select hook in persistence
        //@todo: decide on hooks
        $query = $this->model->toQuery('select');

        $query->where($this->model->getField($this->model->id_field), $id);

        return $query->limit(1)->getRow();
//         $this->scope->addCondition($this->model->id_field, $id);

//         return $this->select()->limit(1)->getRow();
    }

    public function select($fields = []): AbstractQuery
    {
        $this->initFields($fields);
        $this->initWhere();
        $this->initLimit();
        $this->initOrder();

        $this->dsql->mode('select');

        return $this;
    }

    protected function initFields($fields = null)
    {
        // do nothing on purpose
        if ($fields === false) {
            return $this;
        }

        // add fields
        if (is_array($fields)) {
            // Set of fields is strictly defined for purposes of export,
            // so we will ignore even system fields.
            foreach ($fields as $fieldName) {
                $this->addField($this->model->getField($fieldName));
            }
        } elseif ($this->model->only_fields) {
            $addedFields = [];

            // Add requested fields first
            foreach ($this->model->only_fields as $fieldName) {
                $field = $this->model->getField($fieldName);
                if ($field->never_persist) {
                    continue;
                }
                $this->addField($field);
                $addedFields[$fieldName] = true;
            }

            // now add system fields, if they were not added
            foreach ($this->model->getFields() as $fieldName => $field) {
                if ($field->never_persist) {
                    continue;
                }
                if ($field->system && !isset($addedFields[$fieldName])) {
                    $this->addField($field);
                }
            }
        } else {
            foreach ($this->model->getFields() as $fieldName => $field) {
                if ($field->never_persist) {
                    continue;
                }
                $this->addField($field);
            }
        }

        return $this;
    }

    protected function addField(Field $field)
    {
        $this->dsql->field($field, $field->useAlias() ? $field->short_name : null);

        return $this;
    }

    public function insert(): AbstractQuery
    {
        $this->dsql->mode('insert');

        return $this;
    }

    public function update(): AbstractQuery
    {
        $this->dsql->mode('update');

        return $this;
    }

    public function delete(): AbstractQuery
    {
        $this->initWhere();

        $this->dsql->mode('delete');

        return $this;
    }

    public function exists(): AbstractQuery
    {
        $this->initWhere();

        $this->dsql = $this->dsql->dsql()->mode('select')->option('exists')->field($this->dsql);

        return $this;
    }

    public function count($alias = null): AbstractQuery
    {
        $this->initWhere();

        $this->dsql->reset('field')->field('count(*)', $alias);

        return $this;
    }

    public function where($fieldName, $operator = null, $value = null): AbstractQuery
    {
        $this->fillWhere($this->dsql, new Model\Scope\Condition(...func_get_args()));

        return $this;
    }

    public function aggregate($fx, $field, string $alias = null, bool $coalesce = false): AbstractQuery
    {
        $field = is_string($field) ? $this->model->getField($field) : $field;

        $this->initWhere();

        $expr = $coalesce ? "coalesce({$fx}([]), 0)" : "{$fx}([])";

        if (!$alias && $field instanceof FieldSqlExpression) {
            $alias = $fx . '_' . $field->short_name;
        }

        $this->dsql->reset('field')->field($this->dsql->expr($expr, [$field]), $alias);

        return $this;
    }

    public function field($fieldName, string $alias = null): AbstractQuery
    {
        if (!$fieldName) {
            throw new Exception('Field query requires field name');
        }

        $field = is_string($fieldName) ? $this->model->getField($fieldName) : $fieldName;

        if (!$alias && $field instanceof FieldSqlExpression) {
            $alias = $field->short_name;
        }

        $this->initWhere();
        $this->initLimit();
        $this->initOrder();

        if ($this->model->loaded()) {
            $this->dsql->where($this->model->id_field, $this->model->id);
        }

        $this->dsql->reset('field')->field($field, $alias);

        return $this;
    }

    protected function initOrder()
    {
        foreach ((array) $this->order as [$field, $desc]) {
            if (is_string($field)) {
                $field = $this->model->getField($field);
            }

            if (!$field instanceof Expression && !$field instanceof Expressionable) {
                throw (new Exception('Unsupported order parameter'))
                    ->addMoreInfo('model', $this->model)
                    ->addMoreInfo('field', $field);
            }

            $this->dsql->order($field, $desc);
        }

        return $this;
    }

    protected function initLimit()
    {
        if ($args = $this->getLimitArgs()) {
            $this->dsql->reset('limit')->limit(...$args);
        }

        return $this;
    }

    public function execute(): iterable
    {
        return $this->dsql->execute();
    }

    public function get(): array
    {
        return $this->dsql->get();
    }

    public function getRow(): ?array
    {
        return $this->dsql->getRow();
    }

    public function getOne()
    {
        return $this->dsql->getOne();
    }

    public function getDsqlExpression($expression = null)
    {
        return $this->dsql;
    }

    public function dsql()
    {
        return $this->dsql;
    }

    public function model()
    {
        return $this->model;
    }

    protected function initWhere()
    {
        $this->fillWhere($this->dsql, $this->scope);

        return $this;
    }

    protected static function fillWhere(DsqlQuery $query, Model\Scope\AbstractScope $condition)
    {
        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                $query->where(...$condition->toQueryArguments());
            }

            // nested conditions
            if ($condition instanceof Model\Scope) {
                $expression = $condition->isOr() ? $query->orExpr() : $query->andExpr();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    self::fillWhere($expression, $nestedCondition);
                }

                $query->where($expression);
            }
        }
    }

    public function getIterator(): iterable
    {
        try {
            return $this->select()->execute();
        } catch (\PDOException $e) {
            throw (new Exception('Unable to execute iteration query', 0, $e))
                ->addMoreInfo('query', $this->getDebugQuery())
                ->addMoreInfo('message', $e->getMessage())
                ->addMoreInfo('model', $this->model)
                ->addMoreInfo('scope', $this->model->scope()->toWords());
        }
    }

    public function getDebug(): string
    {
        return $this->dsql->getDebugQuery();
    }

    public function __call($method, $args)
    {
        return $this->dsql->{$method}(...$args);
    }
}
