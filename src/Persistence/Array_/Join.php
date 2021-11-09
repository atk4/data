<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * @property Persistence\Array_|null $persistence
 */
class Join extends Model\Join
{
    /**
     * This method is to figure out stuff.
     */
    protected function init(): void
    {
        parent::init();

        // add necessary hooks
        if ($this->reverse) {
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
        } else {
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']));
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    protected function makeFakeModelWithForeignTable(): Model
    {
        $this->getOwner()->assertIsModel();

        $modelCloned = clone $this->getOwner();
        $modelCloned->table = $this->foreign_table;

        // @TODO hooks will be fixed on a cloned model, Join should be replaced later by supporting unioned table as a table model

        return $modelCloned;
    }

    public function afterLoad(Model $entity): void
    {
        // we need to collect ID
        $this->id = $entity->getDataRef()[$this->master_field];
        if (!$this->id) {
            return;
        }

        try {
            $data = Persistence\Array_::assertInstanceOf($this->getOwner()->persistence)
                ->load($this->makeFakeModelWithForeignTable(), $this->id);
        } catch (Exception $e) {
            throw (new Exception('Unable to load joined record', $e->getCode(), $e))
                ->addMoreInfo('table', $this->foreign_table)
                ->addMoreInfo('id', $this->id);
        }
        $dataRef = &$entity->getDataRef();
        $dataRef = array_merge($data, $entity->getDataRef());
    }

    public function beforeInsert(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if ($entity->hasField($this->master_field) && $entity->get($this->master_field)) {
            // The value for the master_field is set,
            // we are going to use existing record.
            return;
        }

        // Figure out where are we going to save data
        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $this->id = $persistence->insert(
            $this->makeFakeModelWithForeignTable(),
            $this->save_buffer
        );

        $data[$this->master_field] = $this->id;

        // $entity->set($this->master_field, $this->id);
    }

    /**
     * @param mixed $id
     */
    public function afterInsert(Model $entity, $id): void
    {
        if ($this->weak) {
            return;
        }

        $this->save_buffer[$this->foreign_field] = $this->hasJoin() ? $this->getJoin()->id : $id;

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $this->id = $persistence->insert(
            $this->makeFakeModelWithForeignTable(),
            $this->save_buffer
        );
    }

    public function beforeUpdate(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $this->id = $persistence->update(
            $this->makeFakeModelWithForeignTable(),
            $this->id,
            $this->save_buffer,
            $this->foreign_table
        );
    }

    public function doDelete(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $persistence->delete(
            $this->makeFakeModelWithForeignTable(),
            $this->id
        );

        $this->id = null;
    }
}
