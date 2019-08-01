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
     * @var int Specify how many decimal numbers should be saved.
     */
    public $decimal_numbers = 0;

    /**
     * @var bool Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $enable_rounding = false;

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

    /**
     * Return array of seed properties of this Field object.
     *
     * @param array $properties Properties to return, by default will return all properties set.
     *
     * @return array
     */
    public function getSeed(array $properties = []) : array
    {
        $seed = parent::getSeed($properties);

        // [key => default_value]
        $properties = $properties ?: [
            'decimal_numbers' => 0,
            'enable_rounding' => false,
        ];

        foreach ($properties as $k=>$v) {
            if ($this->{$k} !== $v) {
                $seed[$k] = $this->{$k};
            }
        }

        return $seed;
    }
}
