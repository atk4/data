<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class Join extends Model\Join
{
    /**
     * By default we create ON expression ourselves, but it can be specific explicitly.
     *
     * @var Expressionable|string|null
     */
    protected $on;

    protected function init(): void
    {
        parent::init();

        $this->getOwner()->persistenceData['use_table_prefixes'] = true; // TODO thus mutates the owner model!

        // our short name will be unique
        if (!$this->foreignAlias) {
            $this->foreignAlias = ($this->getOwner()->tableAlias ?: '') . $this->shortName;
        }

        // Master field indicates ID of the joined item. In the past it had to be
        // defined as a physical field in the main table. Now it is a model field
        // so you can use expressions or fields inside joined entities.
        // If string specified here does not point to an existing model field
        // a new basic field is inserted and marked hidden.
        if (!$this->reverse && !$this->getOwner()->hasField($this->master_field)) {
            $owner = $this->hasJoin() ? $this->getJoin() : $this->getOwner();

            $field = $owner->addField($this->master_field, ['system' => true, 'read_only' => true]);

            $this->master_field = $field->shortName;
        }
    }

    protected function initJoinHooks(): void
    {
        parent::initJoinHooks();

        $this->onHookToOwnerBoth(Persistence\Sql::HOOK_INIT_SELECT_QUERY, \Closure::fromCallable([$this, 'initSelectQuery']));
    }

    /**
     * Before query is executed, this method will be called.
     */
    public function initSelectQuery(Model $model, Query $query): void
    {
        // if ON is set, we don't have to worry about anything
        if ($this->on) {
            $query->join(
                $this->foreignTable,
                $this->on instanceof Expressionable ? $this->on : $this->getOwner()->expr($this->on),
                $this->kind,
                $this->foreignAlias
            );

            return;
        }

        $query->join(
            $this->foreignTable,
            $this->getOwner()->expr('{{}}.{} = {}', [
                $this->foreignAlias ?: $this->foreignTable,
                $this->foreign_field,
                $this->getOwner()->getField($this->master_field),
            ]),
            $this->kind,
            $this->foreignAlias
        );

        /*
        if ($this->reverse) {
            $query->field([$this->shortName => (
                $this->join ?: ($model->tableAlias ?: $model->table) . '.' . $this->master_field
            )]);
        } else {
            $query->field([$this->shortName => $this->foreignAlias . '.' . $this->foreign_field]);
        }
        */
    }

    public function afterLoad(Model $entity): void
    {
        // we need to collect ID
        if (isset($entity->getDataRef()[$this->shortName])) {
            $this->setId($entity, $entity->getDataRef()[$this->shortName]);
            unset($entity->getDataRef()[$this->shortName]);
        }
    }
}
