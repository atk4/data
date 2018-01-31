<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Join_SQL extends Join implements \atk4\dsql\Expressionable
{
    /**
     * By default we create ON expression ourselves, but if you want to specify
     * it, use the 'on' property.
     *
     * @var \atk4\dsql\Expression
     */
    protected $on = null;

    /**
     * Will use either foreign_alias or create #join_<table>.
     *
     * @return string
     */
    public function getDesiredName()
    {
        return '_'.($this->foreign_alias ?: $this->foreign_table[0]);
    }

    /**
     * Returns DSQL Expression.
     *
     * @param \atk4\dsql\Expression $q
     *
     * @return \atk4\dsql\Expression
     */
    public function getDSQLExpression($q)
    {
        /*
        // If our Model has expr() method (inherited from Persistence_SQL) then use it
        if ($this->owner->hasMethod('expr')) {
            return $this->owner->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
        }

        // Otherwise call it from expression itself
        return $q->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
        */

        // Romans: join_sql shouldn't even be called if expr is undefined. I think we should leave it here to produce error.
        return $this->owner->expr('{}.{}', [$this->foreign_alias, $this->foreign_field]);
    }

    /**
     * This method is to figure out stuff.
     */
    public function init()
    {
        parent::init();

        $this->owner->persistence_data['use_table_prefixes'] = true;

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        // Our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = (isset($this->owner->table_alias) ? $this->owner->table_alias : '').$this->short_name;
        }

        $this->owner->addhook('initSelectQuery', $this);

        // Add necessary hooks
        if ($this->reverse) {
            $this->owner->addHook('afterInsert', $this);
            $this->owner->addHook('beforeUpdate', $this);
            $this->owner->addHook('beforeDelete', [$this, 'doDelete'], null, -5);
            $this->owner->addHook('afterLoad', $this);
        } else {

            // Master field indicates ID of the joined item. In the past it had to be
            // defined as a physical field in the main table. Now it is a model field
            // so you can use expressions or fields inside joined entities.
            // If string specified here does not point to an existing model field
            // a new basic field is inserted and marked hidden.
            if (is_string($this->master_field)) {
                $e = $this->owner->hasElement($this->master_field);
                if (!$e) {
                    if ($this->join) {
                        $e = $this->join->addField($this->master_field, ['system' => true, 'read_only' => true]);
                    } else {
                        $e = $this->owner->addField($this->master_field, ['system' => true, 'read_only' => true]);
                    }
                    $this->master_field = $e->short_name;
                }
            }

            $this->owner->addHook('beforeInsert', $this, null, -5);
            $this->owner->addHook('beforeUpdate', $this);
            $this->owner->addHook('afterDelete', [$this, 'doDelete']);
            $this->owner->addHook('afterLoad', $this);
        }
    }

    /**
     * Returns DSQL query.
     *
     * @return \atk4\dsql\Query
     */
    public function dsql()
    {
        $dsql = $this->owner->persistence->initQuery($this->owner);
        $dsql->reset('table');
        $dsql->table($this->foreign_table, $this->foreign_alias);

        return $dsql;
    }

    /**
     * Before query is executed, this method will be called.
     *
     * @param Model            $model
     * @param \atk4\dsql\Query $query
     */
    public function initSelectQuery($model, $query)
    {
        // if ON is set, we don't have to worry about anything
        if ($this->on) {
            $query->join(
                $this->foreign_table.' '.$this->foreign_alias,
                $this->on instanceof \atk4\dsql\Expression ? $this->on : $model->expr($this->on),
                $this->kind
            );

            return;
        }

        $query->join(
            $this->foreign_table.(isset($this->foreign_alias) ? (' '.$this->foreign_alias) : ''),
            $model->expr('{}.{} = {}', [
                (isset($this->foreign_alias) ? $this->foreign_alias : $this->foreign_table),
                $this->foreign_field,
                $this->owner->getElement($this->master_field),
            ]),
            $this->kind
        );

        /*
        if ($this->reverse) {
            $query->field([$this->short_name => ($this->join ?:
                (
                    (isset($this->owner->table_alias) ? $this->owner->table_alias : $this->owner->table)
                    .'.'.$this->master_field)
            )]);
        } else {
            $query->field([$this->short_name => $this->foreign_alias.'.'.$this->foreign_field]);
        }
         */
    }

    /**
     * Called from afterLoad hook.
     *
     * @param Model $model
     */
    public function afterLoad($model)
    {
        // we need to collect ID
        if (isset($model->data[$this->short_name])) {
            $this->id = $model->data[$this->short_name];
            unset($model->data[$this->short_name]);
        }
    }

    /**
     * Called from beforeInsert hook.
     *
     * @param Model $model
     * @param array $data
     */
    public function beforeInsert($model, &$data)
    {
        if ($this->weak) {
            return;
        }

        // The value for the master_field is set, so we are going to use existing record anyway
        if ($model->hasElement($this->master_field) && $model[$this->master_field]) {
            return;
        }

        $insert = $this->dsql();
        $insert->mode('insert');
        $insert->set($model->persistence->typecastSaveRow($model, $this->save_buffer));
        $this->save_buffer = [];
        $insert->set($this->foreign_field, null);
        $insert->insert();
        $this->id = $this->owner->lastInsertID();

        if ($this->join) {
            $this->join->set($this->master_field, $this->id);
        } else {
            $data[$this->master_field] = $this->id;
        }
    }

    /**
     * Called from afterInsert hook.
     *
     * @param Model $model
     * @param mixed $id
     */
    public function afterInsert($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $insert = $this->dsql();
        $insert->set($model->persistence->typecastSaveRow($model, $this->save_buffer));
        $this->save_buffer = [];
        $insert
            ->set(
                $this->foreign_field,
                isset($this->join) ? $this->join->id : $id
            );
        $insert->insert();
        $this->id = $this->owner->lastInsertID();
    }

    /**
     * Called from beforeUpdate hook.
     *
     * @param Model $model
     * @param array $data
     */
    public function beforeUpdate($model, &$data)
    {
        if ($this->weak) {
            return;
        }

        if (!$this->save_buffer) {
            return;
        }

        $update = $this->dsql();
        $update->set($model->persistence->typecastSaveRow($model, $this->save_buffer));
        $this->save_buffer = [];

        if ($this->reverse) {
            $update->where($this->foreign_field, $model->id);
        } else {
            $update->where($this->foreign_field, $model[$this->master_field]);
        }

        $update->update();
    }

    /**
     * Called from beforeDelete and afterDelete hooks.
     *
     * @param Model $model
     * @param mixed $id
     */
    public function doDelete($model, $id)
    {
        if ($this->weak) {
            return;
        }

        $delete = $this->dsql();
        if ($this->reverse) {
            $delete->where($this->foreign_field, $this->owner->id);
        } else {
            $delete->where($this->foreign_field, $this->owner[$this->master_field]);
        }

        $delete->delete()->execute();
    }
}
