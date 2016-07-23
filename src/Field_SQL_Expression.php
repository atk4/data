<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Field_SQL_Expression extends Field_SQL
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }

    /**
     * Used expression.
     *
     * @var mixed
     */
    public $expr = null;

    /**
     * Is field editable?
     * Expression fields can't be editable.
     *
     * @var bool
     */
    public $editable = false;

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
     * @param \atk\dsql\Expression $expression
     *
     * @return \atk\dsql\Expression
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
