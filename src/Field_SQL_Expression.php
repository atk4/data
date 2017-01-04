<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\core\InitializerTrait;

/**
 * Class description?
 */
class Field_SQL_Expression extends Field_SQL
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Used expression.
     *
     * @var mixed
     */
    public $expr = null;

    /**
     * Expressions are always read_only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * Initialization.
     */
    public function init()
    {
        $this->_init();

        if ($this->owner->reload_after_save === null) {
            $this->owner->reload_after_save = true;
        }
    }

    /**
     * Should this field use alias?
     * Expression fields always need alias.
     *
     * @return bool
     */
    public function useAlias()
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
    public function getDSQLExpression($expression)
    {
        $expr = $this->expr;
        if (is_callable($expr)) {
            $c = $this->expr;
            $expr = $c($this->owner, $expression);
        }

        if (is_string($expr)) {
            return $expression->expr('([])', [
                $this->owner->expr($expr),
            ]);
        }

        return $expr;
    }
}
