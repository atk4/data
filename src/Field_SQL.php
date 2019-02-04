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
}
