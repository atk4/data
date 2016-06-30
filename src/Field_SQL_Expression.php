<?php

namespace atk4\data;

class Field_SQL_Expression extends Field_SQL {

    public $expr = null;

    public function useAlias()
    {
        return true;
    }

    /**
     * When field is used as expression, this method will be called
     */
    function getDSQLExpression($expression)
    {
        if (is_string($this->expr)) {
            return $expression->expr('([])', [
                $this->owner->expr($this->expr),
            ]);
        }

        if (is_callable($this->expr)) {
            $c = $this->expr;
            return $c($expression);
        }

        return $this->expr;
    }
}

