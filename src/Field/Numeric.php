<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Basic numeric field type. Think of it as field type "float" in past.
 */
class Numeric extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'float';

    /**
     * @var int Specify how many decimal numbers should be saved.
     */
    public $decimals = 8;

    /**
     * @var bool Enable number rounding. If true will round number, otherwise will round it down (trim).
     */
    public $round = true;

    /**
     * @var bool Set this to `true` if you wish to also store negative values.
     */
    public $signum = true;

    /**
     * @var int specify a minimum value for this number.
     */
    public $min;

    /**
     * @var int specify a maximum value for this number.
     */
    public $max;

    protected static $seedProperties = [
        'decimals',
        'round',
        'signum',
        'min',
        'max',
    ];

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
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be null or empty']);
            }

            return;
        }

        if (!is_scalar($value)) {
            throw new ValidationException([$this->name => 'Must use scalar value']);
        }

        // we clear out thousand separator, but will change to
        // http://php.net/manual/en/numberformatter.parse.php
        // in the future with the introduction of locale
        $value = trim(str_replace(["\r", "\n"], '', $value));
        $value = preg_replace('/[,`\']/', '', $value);

        if (!is_numeric($value)) {
            throw new ValidationException([$this->name => 'Must be numeric']);
        }

        $value = (float) $value;
        $value = $this->round ? round($value, $this->decimals) : $this->roundDown($value, $this->decimals);

        if (!$this->signum && $value < 0) {
            throw new ValidationException([$this->name => 'Must be positive']);
        }

        if ($this->min !== null && $value < $this->min) {
            throw new ValidationException([$this->name => 'Must be greater than or equal to '.$this->min]);
        }

        if ($this->max !== null && $value > $this->max) {
            throw new ValidationException([$this->name => 'Must be less than or equal to '.$this->max]);
        }

        return $value;
    }

    /**
     * Round up to the nearest number.
     *
     * @param float $number Number
     * @param int   $precision Precision
     *
     * @return float
     */
    protected function roundUp(float $number, int $precision): float
    {
        return $precision ? ceil($number / $precision) * $precision : ceil($number);
    }

    /**
     * Round down to the nearest number.
     *
     * @param float $number Number
     * @param int   $precision Precision
     *
     * @return float
     */
    protected function roundDown(float $number, int $precision): float
    {
        return $precision ? floor($number / $precision) * $precision : floor($number);
    }
}
