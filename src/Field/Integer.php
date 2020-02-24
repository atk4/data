<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\ValidationException;

/**
 * Integer field type.
 */
class Integer extends Numeric
{
    /** @var string Field type for backward compatibility. */
    public $type = 'integer';

    /**
     * @var int Specify how many decimal numbers should be saved.
     */
    public $decimals = 0;

    /**
     * @var bool Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $round = false;

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function normalize($value)
    {
        $value = parent::normalize($value);

        return $value === null ? null : (int) $value;
    }
}
