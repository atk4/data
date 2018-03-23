<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\core\InitializerTrait;
use atk4\data\ValidationException;

/**
 * Your favourite nullable binary type.
 */
class Boolean extends \atk4\data\Field
{
    use InitializerTrait {
        init as _init;
    }

    public $valueTrue = null;

    public $valueFalse = null;

    public function __construct()
    {
        // Backwards compatibility
        if ($this->enum) {
            $this->valueFalse = $this->enum[0];
            $this->valueTrue = $this->enum[1];
            unset($this->enum);
        }
    }

    public function normalize($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === $valueTrue) {
            return true;
        }

        if ($value === $valueFalse) {
            return false;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        throw new ValidationException([$this->name => 'Must be a boolean value']);
    }

    // TODO: REVIEW
    public function validate($value)
    {
        if ($this->required && empty($value)) {
            throw new ValidationException([$this->name => 'Must be selected']);
        }
    }
}
