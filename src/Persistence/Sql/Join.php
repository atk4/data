<?php

declare(strict_types=1);

namespace atk4\data\Persistence\Sql;

use atk4\data\Model;
use atk4\data\Persistence;
use atk4\dsql\Expression;
use atk4\dsql\Query;

/**
 * Provides model joining functionality specific for the Sql persistence.
 *
 * @property Persistence\Sql $persistence
 * @property static          $join
 */
class Join extends Model\Join implements \atk4\dsql\Expressionable
{
    /**
     * By default we create ON expression ourselves, but if you want to specify
     * it, use the 'on' property.
     *
     * @var \atk4\dsql\Expression
     */
    protected $on;

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '_' . ($this->foreign_alias ?: $this->foreign_table[0]);
    }

    /**
     * Returns DSQL Expression.
     *
     * @param \atk4\dsql\Expression $q
     */
    public function getDsqlExpression($q): Expression
    {
        /*
        // If our Model has expr() method (inherited from Persistence\Sql) then use it
        if ($this->owner->hasMethod('expr')) {
            return $this->owner->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
        }

        // Otherwise call it from expression itself
        return $q->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
        */

        // Romans: Join\Sql shouldn't even be called if expr is undefined. I think we should leave it here to produce error.
        return $this->owner->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
    }

    /**
     * This method is to figure out stuff.
     */
    protected function init(): void
    {
        parent::init();

        $this->owner->persistence_data['use_table_prefixes'] = true;

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        // Our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = ($this->owner->table_alias ?: '') . $this->short_name;
        }

        $this->onHookToOwner(Persistence\Sql::HOOK_INIT_SELECT_QUERY, \Closure::fromCallable([$this, 'initSelectQuery']));

        // Add necessary hooks
        if ($this->reverse) {
            $this->onHookToOwner(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']));
            $this->onHookToOwner(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookToOwner(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
            $this->onHookToOwner(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        } else {
            // Master field indicates ID of the joined item. In the past it had to be
            // defined as a physical field in the main table. Now it is a model field
            // so you can use expressions or fields inside joined entities.
            // If string specified here does not point to an existing model field
            // a new basic field is inserted and marked hidden.
            if (is_string($this->master_field)) {
                if (!$this->owner->hasField($this->master_field)) {
                    if ($this->join) {
                        $f = $this->join->addField($this->master_field, ['system' => true, 'read_only' => true]);
                    } else {
                        $f = $this->owner->addField($this->master_field, ['system' => true, 'read_only' => true]);
                    }
                    $this->master_field = $f->short_name;
                }
            }

            $this->onHookToOwner(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']), [], -5);
            $this->onHookToOwner(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookToOwner(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));
            $this->onHookToOwner(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    /**
     * Returns DSQL query.
     */
    public function dsql(): Query
    {
        $dsql = $this->owner->persistence->initQuery($this->owner);

        return $dsql->reset('table')->table($this->foreign_table, $this->foreign_alias);
    }

    /**
     * Before query is executed, this method will be called.
     */
    public function initSelectQuery(Model $model, Query $query): void
    {
        // if ON is set, we don't have to worry about anything
        if ($this->on) {
            $query->join(
                $this->foreign_table,
                $this->on instanceof \atk4\dsql\Expression ? $this->on : $model->expr($this->on),
                $this->kind,
                $this->foreign_alias
            );

            return;
        }

        $query->join(
            $this->foreign_table,
            $model->expr('{{}}.{} = {}', [
                ($this->foreign_alias ?: $this->foreign_table),
                $this->foreign_field,
                $this->owner->getField($this->master_field),
            ]),
            $this->kind,
            $this->foreign_alias
        );

        /*
        if ($this->reverse) {
            $query->field([$this->short_name => ($this->join ?:
                (
                    ($this->owner->table_alias ?: $this->owner->table)
                    .'.'.$this->master_field)
            )]);
        } else {
            $query->field([$this->short_name => $this->foreign_alias.'.'.$this->foreign_field]);
        }
         */
    }

    /**
     * Called from afterLoad hook.
     */
    public function afterLoad(Model $model): void
    {
        // we need to collect ID
        if (isset($model->data[$this->short_name])) {
            $this->id = $model->data[$this->short_name];
            unset($model->data[$this->short_name]);
        }
    }

    /**
     * Called from beforeInsert hook.
     */
    public function beforeInsert(Model $model, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        // The value for the master_field is set, so we are going to use existing record anyway
        if ($model->hasField($this->master_field) && $model->get($this->master_field)) {
            return;
        }

        $query = $this->dsql();
        $query->mode('insert');
        $query->set($model->persistence->typecastSaveRow($model, $this->save_buffer));
        $this->save_buffer = [];
        $query->set($this->foreign_field, null);
        $query->insert();
        $this->id = $this->owner->persistence->lastInsertId($this->owner);

        if ($this->join) {
            $this->join->set($this->master_field, $this->id);
        } else {
            $data[$this->master_field] = $this->id;
        }
    }

    /**
     * Called from afterInsert hook.
     *
     * @param mixed $id
     */
    public function afterInsert(Model $model, $id): void
    {
        if ($this->weak) {
            return;
        }

        $query = $this->dsql();
        $query->set($model->persistence->typecastSaveRow($model, $this->save_buffer));
        $this->save_buffer = [];
        $query->set($this->foreign_field, $this->join->id ?? $id);
        $query->insert();
        $this->id = $this->owner->persistence->lastInsertId($this->owner);
    }

    /**
     * Called from beforeUpdate hook.
     */
    public function beforeUpdate(Model $model, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if (!$this->save_buffer) {
            return;
        }

        $query = $this->dsql();
        $query->set($model->persistence->typecastSaveRow($model, $this->save_buffer));
        $this->save_buffer = [];

        $id = $this->reverse ? $model->getId() : $model->get($this->master_field);

        $query->where($this->foreign_field, $id)->update();
    }

    /**
     * Called from beforeDelete and afterDelete hooks.
     *
     * @param mixed $id
     */
    public function doDelete(Model $model, $id): void
    {
        if ($this->weak) {
            return;
        }

        $id = $this->reverse ? $this->owner->getId() : $this->owner->get($this->master_field);

        $this->dsql()->where($this->foreign_field, $id)->delete();
    }
}
