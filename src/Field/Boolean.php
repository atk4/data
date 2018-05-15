<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\core\InitializerTrait;
use atk4\data\Exception;
use atk4\data\ValidationException;

/**
 * Your favorite nullable binary type.
 */
class Boolean extends \atk4\data\Field
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Value which will be used for "true".
     *
     * @var mixed
     */
    public $valueTrue = true;

    /**
     * Value which will be used for "false".
     *
     * @var mixed
     */
    public $valueFalse = false;

    /**
     * Constructor.
     */
    public function __construct()
    {
        // Backwards compatibility
        if (isset($this->enum)) {
            $this->valueFalse = $this->enum[0];
            $this->valueTrue = $this->enum[1];
            unset($this->enum);
        }
    }

    /**
     * Normalize value to boolean value.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function normalize($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === $this->valueTrue) {
            return true;
        }

        if ($value === $this->valueFalse) {
            return false;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        throw new ValidationException([$this->name => 'Must be a boolean value']);
    }

    /**
     * Validate if value is allowed for this field.
     *
     * @param mixed $value
     */
    public function validate($value)
    {
        // if value required, then only valueTrue is allowed
        if ($this->required && $value !== $this->valueTrue) {
            throw new ValidationException([$this->name => 'Must be selected']);
        }
    }
}
