<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Model;
use Atk4\Data\Persistence\SqlPersistence;

/**
 * @property SqlPersistence $persistence
 */
class Join extends Model\Join
{
    /**
     * By default we create ON expression ourselves, but if you want to specify
     * it, use the 'on' property.
     *
     * @var \Atk4\Data\Persistence\Sql\Expression|string|null
     */
    protected $on;

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '_' . ($this->foreign_alias ?: $this->foreign_table);
    }

    /**
     * This method is to figure out stuff.
     */
    protected function init(): void
    {
        parent::init();

        $this->getOwner()->persistence_data['use_table_prefixes'] = true;

        // our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = ($this->getOwner()->table_alias ?: '') . $this->short_name;
        }

        $this->onHookToOwnerBoth(SqlPersistence::HOOK_INIT_SELECT_QUERY, \Closure::fromCallable([$this, 'initSelectQuery']));

        // add necessary hooks
        if ($this->reverse) {
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_INSERT, \Closure::fromCallable([$this, 'afterInsert']));
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_DELETE, \Closure::fromCallable([$this, 'doDelete']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        } else {
            // Master field indicates ID of the joined item. In the past it had to be
            // defined as a physical field in the main table. Now it is a model field
            // so you can use expressions or fields inside joined entities.
            // If string specified here does not point to an existing model field
            // a new basic field is inserted and marked hidden.
            if (!$this->getOwner()->hasField($this->master_field)) {
                $owner = $this->hasJoin() ? $this->getJoin() : $this->getOwner();

                $field = $owner->addField($this->master_field, ['system' => true, 'read_only' => true]);

                $this->master_field = $field->short_name;
            }

            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_INSERT, \Closure::fromCallable([$this, 'beforeInsert']), [], -5);
            $this->onHookToOwnerEntity(Model::HOOK_BEFORE_UPDATE, \Closure::fromCallable([$this, 'beforeUpdate']));
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_DELETE, \Closure::fromCallable([$this, 'doDelete']));
            $this->onHookToOwnerEntity(Model::HOOK_AFTER_LOAD, \Closure::fromCallable([$this, 'afterLoad']));
        }
    }

    /**
     * Returns DSQL query.
     */
    public function dsql(): Query
    {
        $dsql = $this->getOwner()->persistence->initQuery($this->getOwner());

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
                $this->on instanceof \Atk4\Data\Persistence\Sql\Expression ? $this->on : $this->getOwner()->expr($this->on),
                $this->kind,
                $this->foreign_alias
            );

            return;
        }

        $query->join(
            $this->foreign_table,
            $this->getOwner()->expr('{{}}.{} = {}', [
                ($this->foreign_alias ?: $this->foreign_table),
                $this->foreign_field,
                $this->getOwner()->getField($this->master_field),
            ]),
            $this->kind,
            $this->foreign_alias
        );

        /*
        if ($this->reverse) {
            $query->field([$this->short_name => (
                $this->join ?: ($model->table_alias ?: $model->table) . '.' . $this->master_field
            )]);
        } else {
            $query->field([$this->short_name => $this->foreign_alias . '.' . $this->foreign_field]);
        }
        */
    }

    public function afterLoad(Model $entity): void
    {
        // we need to collect ID
        if (isset($entity->getDataRef()[$this->short_name])) {
            $this->setId($entity, $entity->getDataRef()[$this->short_name]);
            unset($entity->getDataRef()[$this->short_name]);
        }
    }

    public function beforeInsert(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        // The value for the master_field is set, so we are going to use existing record anyway
        if ($model->hasField($this->master_field) && $entity->get($this->master_field)) {
            return;
        }

        $query = $this->dsql();
        $query->mode('insert');
        $query->setMulti($model->persistence->typecastSaveRow($model, $this->getAndUnsetSaveBuffer($entity)));
        // $query->set($this->foreign_field, null);
        $query->insert();
        $this->setId($entity, $model->persistence->lastInsertId(new Model($model->persistence, ['table' => $this->foreign_table])));

        if ($this->hasJoin()) {
            $this->getJoin()->setSaveBufferValue($entity, $this->master_field, $this->getId($entity));
        } else {
            $data[$this->master_field] = $this->getId($entity);
        }
    }

    public function afterInsert(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $model = $this->getOwner();

        $query = $this->dsql();
        $query->setMulti($model->persistence->typecastSaveRow($model, $this->getAndUnsetSaveBuffer($entity)));
        $query->set($this->foreign_field, $this->hasJoin() ? $this->getJoin()->getId($entity) : $entity->getId());
        $query->insert();
        $this->setId($entity, $model->persistence->lastInsertId($model));
    }

    public function beforeUpdate(Model $entity, array &$data): void
    {
        if ($this->weak) {
            return;
        }

        if (!$this->issetSaveBuffer($entity)) {
            return;
        }

        $model = $this->getOwner();

        $query = $this->dsql();
        $query->setMulti($model->persistence->typecastSaveRow($model, $this->getAndUnsetSaveBuffer($entity)));

        $id = $this->reverse ? $entity->getId() : $entity->get($this->master_field);

        $query->where($this->foreign_field, $id)->update();
    }

    public function doDelete(Model $entity): void
    {
        if ($this->weak) {
            return;
        }

        $query = $this->dsql();
        $id = $this->reverse ? $entity->getId() : $entity->get($this->master_field);

        $query->where($this->foreign_field, $id)->delete();
    }
}
