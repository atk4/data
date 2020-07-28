<?php

declare(strict_types=1);

namespace atk4\data\Persistence\Array_;

use atk4\data\Exception;
use atk4\data\Model;

/**
 * Provides model joining functionality specific for the Array_ persistence.
 */
class Join extends Model\Join
{
    /**
     * This method is to figure out stuff.
     */
    public function init(): void
    {
        parent::init();

        // If kind is not specified, figure out join type
        if (!isset($this->kind)) {
            $this->kind = $this->weak ? 'left' : 'inner';
        }

        // Add necessary hooks
        if ($this->reverse) {
            $this->owner->onHook(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']), [], -5);
            $this->owner->onHook(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->owner->onHook(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
        } else {
            $this->owner->onHook(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']));
            $this->owner->onHook(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->owner->onHook(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));
            $this->owner->onHook(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
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
            throw (new Exception('Unable to load joined record', $e->getCode(), $e))
                ->addMoreInfo('table', $this->foreign_table)
                ->addMoreInfo('id', $this->id);
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

        if ($model->hasField($this->master_field) && $model->get($this->master_field)) {
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

        $this->save_buffer[$this->foreign_field] = isset($this->join) ? $this->join->id : $id;

        $persistence = $this->persistence ?: $this->owner->persistence;

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

        $persistence = $this->persistence ?: $this->owner->persistence;

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

        $persistence = $this->persistence ?: $this->owner->persistence;

        $persistence->delete(
            $model,
            $this->id,
            $this->foreign_table
        );

        $this->id = null;
    }
}
