<?php

namespace atk4\data;

class Field_SQL_Expression extends Field_SQL
{
    public $expr = null;

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
