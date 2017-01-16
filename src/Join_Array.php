<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Join_Array extends Join
{
    /**
     * This method is to figure out stuff.
     */
    public function init()
    {
        parent::init();

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        // Add necessary hooks
        if ($this->reverse) {
            $this->owner->addHook('afterInsert', $this, null, -5);
            $this->owner->addHook('beforeUpdate', $this, null, -5);
            $this->owner->addHook('beforeDelete', [$this, 'doDelete'], null, -5);
        } else {
            $this->owner->addHook('beforeInsert', $this);
            $this->owner->addHook('beforeUpdate', $this);
            $this->owner->addHook('afterDelete', [$this, 'doDelete']);
            $this->owner->addHook('afterLoad', $this);
        }
    }

    /**
     * Called from afterLoad hook.
     *
     * @param Model $model
     */
    public function afterLoad($model)
    {
        // we need to collect ID
        $this->id = $model->data[$this->master_field];
        if (!$this->id) {
            return;
        }

        try {
            $data = $model->persistence->load($model, $this->id, $this->foreign_table);
        } catch (Exception $e) {
            throw new Exception([
                'Unable to load joined record',
                'table' => $this->foreign_table,
                'id'    => $this->id,
            ], $e->getCode(), $e);
        }
        $model->data = array_merge($data, $model->data);
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

        if ($model->hasElement($this->master_field)
            && $model[$this->master_field]
        ) {
            // The value for the master_field is set,
            // we are going to use existing record.
            return;
        }

        // Figure out where are we going to save data
        $persistence = $this->persistence ?:
            $this->owner->persistence;

        $this->id = $persistence->insert(
            $model,
            $this->save_buffer,
            $this->foreign_table
        );

        $data[$this->master_field] = $this->id;

        //$this->owner->set($this->master_field, $this->id);
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

        $this->save_buffer[$this->foreign_field] =
            isset($this->join) ? $this->join->id : $id;

        $persistence = $this->persistence ?:
            $this->owner->persistence;

        $this->id = $persistence->insert(
            $model,
            $this->save_buffer,
            $this->foreign_table
        );
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

        $persistence = $this->persistence ?:
            $this->owner->persistence;

        $this->id = $persistence->update(
            $model,
            $this->id,
            $this->save_buffer,
            $this->foreign_table
        );
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

        $persistence = $this->persistence ?:
            $this->owner->persistence;

        $persistence->delete(
            $model,
            $this->id,
            $this->foreign_table
        );

        $this->id = null;
    }
}
