<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

/**
 * Class description?
 */
class Field_SQL extends Field implements Expressionable
{
    /**
     * Actual field name.
     *
     * @var string|null
     */
    public $actual = null;

    /**
     * Should this field use alias?
     *
     * @return bool
     */
    public function useAlias()
    {
        return isset($this->actual);
    }

    /**
     * SQL fields are allowed to have expressions inside of them.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value instanceof Expression ||
            $value instanceof Expressionable) {
            return $value;
        }

        return parent::normalize($value);
    }

    /**
     * When field is used as expression, this method will be called.
     *
     * @param Expression $expression
     *
     * @return Expression
     */
    public function getDSQLExpression($expression)
    {
        if (isset($this->owner->persistence_data['use_table_prefixes'])) {
            $mask = '{}.{}';
            $prop = [
                $this->join
                    ? (isset($this->join->foreign_alias)
                        ? $this->join->foreign_alias
                        : $this->join->short_name)
                    : (isset($this->owner->table_alias)
                        ? $this->owner->table_alias
                        : $this->owner->table),
                $this->actual ?: $this->short_name,
            ];
        } else {
            // references set flag use_table_prefixes, so no need to check them here
            $mask = '{}';
            $prop = [
                $this->actual ?: $this->short_name,
            ];
        }

        // If our Model has expr() method (inherited from Persistence_SQL) then use it
        if ($this->owner->hasMethod('expr')) {
            $this->owner->expr($mask, $prop);
        }

        // Otherwise call method from expression
        return $expression->expr($mask, $prop);
    }
}
