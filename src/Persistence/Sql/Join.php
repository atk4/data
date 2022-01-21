<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class Join extends Model\Join
{
    /**
     * By default we create ON expression ourselves, but if you want to specify
     * it, use the 'on' property.
     *
     * @var Expressionable|string|null
     */
    protected $on;

    /**
     * Will use either foreign_alias or create #join_<table>.
     */
    public function getDesiredName(): string
    {
        return '_' . ($this->foreign_alias ?: $this->foreign_table);
    }

    protected function init(): void
    {
        parent::init();

        $this->getOwner()->persistence_data['use_table_prefixes'] = true; // TODO thus mutates the owner model!

        // our short name will be unique
        if (!$this->foreign_alias) {
            $this->foreign_alias = ($this->getOwner()->table_alias ?: '') . $this->short_name;
        }

        // Master field indicates ID of the joined item. In the past it had to be
        // defined as a physical field in the main table. Now it is a model field
        // so you can use expressions or fields inside joined entities.
        // If string specified here does not point to an existing model field
        // a new basic field is inserted and marked hidden.
        if (!$this->reverse && !$this->getOwner()->hasField($this->master_field)) {
            $owner = $this->hasJoin() ? $this->getJoin() : $this->getOwner();

            $field = $owner->addField($this->master_field, ['system' => true, 'read_only' => true]);

            $this->master_field = $field->short_name;
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
                $this->foreign_table,
                $this->on instanceof Expressionable ? $this->on : $this->getOwner()->expr($this->on),
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
}
