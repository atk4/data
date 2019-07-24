<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;

/**
 * Basic numeric field type. Think of it as field type "float" in past.
 */
class Numeric extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'float';

    /**
     * Specify how many decimal numbers should be saved. Set this to 0 for integers.
     */
    public $decimalNumbers = 8;

    /**
     * Set this to `true` if you wish to also store negative values.
     */
    public $signed = true;

    /**
     * @var mixed specify a minimum value for this number.
     */
    public $min;

    /**
     * @var mixed specify a maximum value for this number.
     */
    public $max;

    /**
     * Normalize value to boolean value.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return bool|null
     */
    public function normalize($value)
    {
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be null or empty']);
            }

            return null;
        }

        if (!is_scalar($value)) {
            throw new ValidationException([$this->name => 'Must use scalar value']);
        }

        // remove line-ends and thousand separators
        $value = trim(str_replace(["\r", "\n"], '', $value));
        $value = preg_replace('/[,`\']/', '', $value);

        if (!is_numeric($value)) {
            throw new ValidationException([$this->name => 'Must be numeric']);
        }

        $value = (float) $value;
        $value = round($value, $this->decimalNumbers);

        if (!$this->signed && $value < 0) {
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
}
