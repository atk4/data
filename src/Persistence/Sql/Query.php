<?php

declare(strict_types=1);

namespace atk4\data\Persistence\Sql;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;
use atk4\dsql\Query as DsqlQuery;

/**
 * Class to perform queries on Sql persistence.
 * Utilizes atk4\dsql\Query to perform the operations.
 *
 * @method DsqlQuery getDebugQuery()
 * @method DsqlQuery render()
 * @method DsqlQuery mode()
 * @method DsqlQuery reset()
 * @method DsqlQuery join()
 */
class Query extends Persistence\AbstractQuery implements Expressionable
{
    /** @var DsqlQuery */
    protected $dsql;

    public function __construct(Model $model, Persistence\Sql $persistence = null)
    {
        parent::__construct($model, $persistence);

        $this->dsql = $model->persistence_data['dsql'] = $this->persistence->dsql();

        if ($model->table) {
            $this->dsql->table($model->table, $model->table_alias ?? null);
        }

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
            $subQuery = $withModel->toQuery()->select($fieldsFrom)->dsql();

            // add With cursor
            $this->dsql->with($subQuery, $alias, $fieldsTo ?: null, $recursive);
        }
    }

    protected function initSelect($fields = null): void
    {
        $this->dsql->reset('field');

        // do nothing on purpose
        if ($fields === false) {
            return;
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
    }

    protected function addField(Field $field): void
    {
        $this->dsql->field($field, $field->useAlias() ? $field->short_name : null);
    }

    protected function initInsert(array $data): void
    {
        if ($data) {
            $this->dsql->mode('insert')->set($data);
        }
    }

    protected function initUpdate(array $data): void
    {
        if ($data) {
            $this->dsql->mode('update')->set($data);
        }
    }

    protected function initDelete(): void
    {
        $this->dsql->mode('delete');
    }

    protected function initExists(): void
    {
        $newDsql = $this->dsql->dsql();

        // MSSQL does not support EXISTS everywhere, so wrap in SELECT
        // @todo: move this to connection specific class
        if ($this->persistence->connection instanceof \atk4\dsql\Mssql\Connection) {
            $this->dsql = $newDsql->expr(
                '(select case when exists[] then 1 else 0 end)',
                [$this->dsql]
            );
        } else {
            $this->dsql = $newDsql->mode('select')->option('exists')->field($this->dsql);
        }
    }

    protected function initCount($alias = null): void
    {
        $this->dsql->reset('field')->field('count(*)', $alias);
    }

    protected function initAggregate(string $functionName, $field, string $alias = null, bool $coalesce = false): void
    {
        $field = is_string($field) ? $this->model->getField($field) : $field;

        $expr = $coalesce ? "coalesce({$functionName}([]), 0)" : "{$functionName}([])";

        if (!$alias && $field instanceof FieldSqlExpression) {
            $alias = $functionName . '_' . $field->short_name;
        }

        $this->dsql->reset('field')->field($this->dsql->expr($expr, [$field]), $alias);
    }

    protected function initField($fieldName, string $alias = null): void
    {
        if (!$fieldName) {
            throw new Exception('Field query requires field name');
        }

        $field = is_string($fieldName) ? $this->model->getField($fieldName) : $fieldName;

        if (!$alias && $field instanceof FieldSqlExpression) {
            $alias = $field->short_name;
        }

        $this->dsql->reset('field')->field($field, $alias);
    }

    protected function initOrder(): void
    {
        $this->dsql->reset('order');

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
    }

    protected function initLimit(): void
    {
        $this->dsql->reset('limit');

        if ($args = $this->getLimitArgs()) {
            $this->dsql->limit(...$args);
        }
    }

    protected function doExecute()
    {
        return $this->dsql->execute();
    }

    protected function doGet(): array
    {
        return $this->dsql->get();
    }

    protected function doGetRow(): ?array
    {
        return $this->dsql->getRow();
    }

    protected function doGetOne()
    {
        return $this->dsql->getOne();
    }

    public function getDsqlExpression($expression = null)
    {
        return $this->dsql;
    }

    /**
     * Return the underlying Dsql object performing the query to DB.
     *
     * @return \atk4\dsql\Query
     */
    public function dsql()
    {
        return $this->dsql;
    }

    protected function initWhere(): void
    {
        $this->fillWhere($this->dsql, $this->scope);
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

    public function getDebug(): array
    {
        return array_merge([
            'sql' => $this->dsql->getDebugQuery(),
        ], parent::getDebug());
    }

    public function __call($method, $args)
    {
        return $this->dsql->{$method}(...$args);
    }
}
