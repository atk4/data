<?php

declare(strict_types=1);

namespace atk4\data;

use atk4\core\InitializerTrait;
use atk4\dsql\Expressionable;

/**
 * Class description?
 */
class FieldSqlExpression extends FieldSql
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Used expression.
     *
     * @var mixed
     */
    public $expr;

    /**
     * Expressions are always read_only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * Specifies how to aggregate this.
     */
    public $aggregate;

    /**
     * Aggregation by concatenation.
     */
    public $concat;

    /**
     * When defining as aggregate, this will point to relation object.
     */
    public $aggregate_relation;

    /**
     * Specifies which field to use.
     */
    public $field;

    /**
     * Initialization.
     */
    public function init(): void
    {
        $this->_init();

        if ($this->owner->reload_after_save === null) {
            $this->owner->reload_after_save = true;
        }

        if ($this->concat) {
            $this->owner->onHook(Model::HOOK_AFTER_SAVE, \Closure::fromCallable([$this, 'afterSave']));
        }
    }

    /**
     * Possibly that user will attempt to insert values here. If that is the case, then
     * we would need to inject it into related hasMany relationship.
     *
     * @param Model $m
     */
    public function afterSave($m)
    {
    }

    /**
     * Should this field use alias?
     * Expression fields always need alias.
     */
    public function useAlias(): bool
    {
        return true;
    }

    /**
     * When field is used as expression, this method will be called.
     *
     * @param \atk4\dsql\Expression $expression
     *
     * @return \atk4\dsql\Expression
     */
    public function getDsqlExpression($expression)
    {
        $expr = $this->expr;
        if ($expr instanceof \Closure) {
            $expr = $expr($this->owner, $expression);
        }

        if ($expr instanceof Expressionable) {
            $expr = $expr->getDsqlExpression($expression);
        }

        if (is_string($expr)) {
            // If our Model has expr() method (inherited from Persistence\Sql) then use it
            if ($this->owner->hasMethod('expr')) {
                return $this->owner->expr('([])', [$this->owner->expr($expr)]);
            }

            // Otherwise call it from expression itself
            return $expression->expr('([])', [$expression->expr($expr)]);
        }

        return $expr;
    }
}
