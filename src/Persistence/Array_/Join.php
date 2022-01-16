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

    public function afterLoad(Model $entity): void
    {
        // we need to collect ID
        $this->setId($entity, $entity->getDataRef()[$this->master_field]);
        if ($this->getId($entity) === null) {
            return;
        }

        try {
            $data = Persistence\Array_::assertInstanceOf($this->getOwner()->persistence)
                ->load($this->createFakeForeignModel(), $this->getId($entity));
        } catch (Exception $e) {
            throw (new Exception('Unable to load joined record', $e->getCode(), $e))
                ->addMoreInfo('table', $this->foreign_table)
                ->addMoreInfo('id', $this->getId($entity));
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

        $lastInsertedId = $persistence->insert(
            $this->createFakeForeignModel(),
            $this->getAndUnsetSaveBuffer($entity)
        );
        $this->setId($entity, $lastInsertedId);

        $data[$this->master_field] = $this->getId($entity);

        // $entity->set($this->master_field, $this->getId($entity));
    }

    public function afterInsert(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $this->setSaveBufferValue($entity, $this->foreign_field, $this->hasJoin() ? $this->getJoin()->getId($entity) : $entity->getId());

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $lastInsertedId = $persistence->insert(
            $this->createFakeForeignModel(),
            $this->getAndUnsetSaveBuffer($entity)
        );
        $this->setId($entity, $lastInsertedId);
    }

    public function beforeUpdate(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $persistence->update(
            $this->createFakeForeignModel(),
            $this->getId($entity),
            $this->getAndUnsetSaveBuffer($entity)
        );
        // $this->setId($entity, ??);
    }

    public function doDelete(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $persistence = $this->persistence ?: $this->getOwner()->persistence;

        $persistence->delete(
            $this->createFakeForeignModel(),
            $this->getId($entity)
        );
        $this->unsetId($entity);
    }
}
