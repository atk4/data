<?php

namespace atk4\data;

class Field_SQL_Expression extends Field_SQL
{
    use \atk4\core\InitializerTrait {
        init as _init;
    }

    public $expr = null;

    public $editable = false;

    public function init()
    {
        $this->_init();

        if ($this->owner->reload_after_save === null) {
            $this->owner->reload_after_save = true;
        }
    }

    public function useAlias()
    {
        return true;
    }

    /**
     * When field is used as expression, this method will be called.
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
