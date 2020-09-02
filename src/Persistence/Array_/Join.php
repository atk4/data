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
    protected function init(): void
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
    public function afterLoad($model): void
    {
        // we need to collect ID
        $this->id = $model->data[$this->master_field];
        if (!$this->id) {
            return;
        }

        $data = $this->getPersistence()->getRow($this->getJoinModel(), $this->id);

        if (!$data) {
            throw (new Exception('Unable to load joined record'))
                ->addMoreInfo('table', $this->foreign_table)
                ->addMoreInfo('id', $this->id);
        }

        $model->data = array_merge($data, $model->data);
    }

    /**
     * Called from beforeInsert hook.
     *
     * @param Model $model
     */
    public function beforeInsert($model, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if ($model->hasField($this->master_field) && $model->get($this->master_field)) {
            // The value for the master_field is set,
            // we are going to use existing record.
            return;
        }

        $this->id = $this->getPersistence()->insert(
            $this->getJoinModel(),
            $this->save_buffer
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
    public function afterInsert($model, $id): void
    {
        if ($this->weak) {
            return;
        }

        $this->save_buffer[$this->foreign_field] = isset($this->join) ? $this->join->id : $id;

        $this->id = $this->getPersistence()->insert(
            $this->getJoinModel(),
            $this->save_buffer
        );
    }

    /**
     * Called from beforeUpdate hook.
     *
     * @param Model $model
     */
    public function beforeUpdate($model, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $this->id = $this->getPersistence()->update(
            $this->getJoinModel(),
            $this->id,
            $this->save_buffer
        );
    }

    /**
     * Called from beforeDelete and afterDelete hooks.
     *
     * @param Model $model
     * @param mixed $id
     */
    public function doDelete($model, $id): void
    {
        if ($this->weak) {
            return;
        }

        $this->getPersistence()->delete(
            $this->getJoinModel(),
            $this->id,
        );

        $this->id = null;
    }

    protected function getPersistence()
    {
        return $this->persistence ?: $this->owner->persistence;
    }

    protected function getJoinModel()
    {
        $joinModel = clone $this->owner;
        $joinModel->table = $this->foreign_table;

        return $joinModel;
    }
}
