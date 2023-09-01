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

        // TODO thus mutates the owner model!
        $this->getOwner()->persistenceData['use_table_prefixes'] = true;

        // our short name will be unique
        // TODO this should be removed, short name is not guaranteed to be unique with nested model/query
        if ($this->foreignAlias === null) {
            $this->foreignAlias = ($this->getOwner()->tableAlias ?? '') . '_' . (str_starts_with($this->shortName, '#join-') ? substr($this->shortName, 6) : $this->shortName);
        }

        // TODO thus mutates the owner model/joins!
        if (!$this->reverse && !$this->getOwner()->hasField($this->masterField)) {
            $owner = $this->hasJoin() ? $this->getJoin() : $this->getOwner();
            $field = $owner->addField($this->masterField, ['type' => 'integer', 'system' => true, 'readOnly' => true]);
            $this->masterField = $field->shortName; // TODO thus mutates the join!
        } elseif ($this->reverse && !$this->getOwner()->hasField($this->foreignField) && $this->hasJoin()) {
            $owner = $this->getJoin();
            $field = $owner->addField($this->foreignField, ['type' => 'integer', 'system' => true, 'readOnly' => true, 'actual' => $this->masterField]);
            $this->foreignField = $field->shortName; // TODO thus mutates the join!
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
    protected function initSelectQuery(Model $model, Query $query): void
    {
        if ($this->on) {
            $onExpr = $this->on instanceof Expressionable ? $this->on : $this->getOwner()->expr($this->on);
        } else {
            $onExpr = $this->getOwner()->expr('{{}}.{} = {}', [
                $this->foreignAlias ?? $this->foreignTable,
                $this->foreignField,
                $this->hasJoin()
                    ? $this->getOwner()->expr('{}.{}', [$this->getJoin()->foreignAlias, $this->masterField])
                    : $this->getOwner()->getField($this->masterField),
            ]);
        }

        $query->join(
            $this->foreignTable,
            $onExpr,
            $this->kind,
            $this->foreignAlias
        );
    }
}
