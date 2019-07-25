<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Integer field type.
 */
class Integer extends Numeric
{
    /** @var string Field type for backward compatibility. */
    public $type = 'integer';

    /**
     * Specify how many decimal numbers should be saved.
     */
    public $decimal_numbers = 0;

    /**
     * Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $enable_rounding = false;

    /**
     * Normalize value to integer.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return bool|null
     */
    public function normalize($value)
    {
        $value = parent::normalize($value);

        return $value === null ? null : (int) $value;
    }
}
