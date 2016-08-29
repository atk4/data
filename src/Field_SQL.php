<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Field_SQL extends Field implements \atk4\dsql\Expressionable
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
     * When field is used as expression, this method will be called.
     *
     * @param \atk\dsql\Expression $expression
     *
     * @return \atk\dsql\Expression
     */
    public function getDSQLExpression($expression)
    {
        if (isset($this->owner->persistence_data['use_table_prefixes'])) {
            return $expression->expr('{}.{}', [
                $this->join
                    ? (isset($this->join->foreign_alias)
                        ? $this->join->foreign_alias
                        : $this->join->short_name)
                    : (isset($this->owner->table_alias)
                        ? $this->owner->table_alias
                        : $this->owner->table),
                $this->actual ?: $this->short_name,
            ]);
        } else {
            // references set flag use_table_prefixes, so no need to check them here
            return $expression->expr('{}', [
                $this->actual ?: $this->short_name,
            ]);
        }
    }
}
