<?php

namespace atk4\data;

class Field_SQL extends Field implements \atk4\dsql\Expressionable
{
    /**
     * Actual field name
     *
     * @var string|null
     */
    public $actual = null;

    /**
     * Should this field use alias?
     *
     * @return boolean
     */
    public function useAlias()
    {
        return isset($this->actual);
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
        if (isset($this->owner->persistence_data['use_table_prefixes'])) {
            if ($this->actual) {
                return $expression->expr('{}.{}', [
                    $this->join ? (isset($this->join->foreign_alias) ? $this->join->foreign_alias : $this->join->short_name)
                    : ($this->owner->table_alias ?: $this->owner->table),
                    $this->actual,
                ]);
            } else {
                return $expression->expr('{}.{}', [
                    $this->join ? (isset($this->join->foreign_alias) ? $this->join->foreign_alias : $this->join->short_name)
                    : (isset($this->owner->table_alias) ? $this->owner->table_alias : $this->owner->table),
                    $this->short_name,
                ]);
            }
        } else {
            // relations set flag use_table_prefixes, so no need to check them here
            if ($this->actual) {
                return $expression->expr('{}', [
                    $this->actual,
                ]);
            } else {
                return $expression->expr('{}', [
                    $this->short_name,
                ]);
            }
        }
    }
}
