<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\core\InitializerTrait;
use atk4\data\ValidationException;

/**
 * Your favorite nullable binary type.
 */
class Boolean extends \atk4\data\Field
{
    use InitializerTrait {
        init as _init;
    }

    /** @var string Field type for backward compatibility. */
    public $type = 'boolean';

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
     * Backward compatible way to specify value for true / false:.
     *
     * $enum = ['N', 'Y']
     *
     * @var array
     */
    public $enum = null;

    /**
     * Constructor.
     */
    public function init()
    {
        $this->_init();

        // Backwards compatibility
        if ($this->enum) {
            $this->valueFalse = $this->enum[0];
            $this->valueTrue = $this->enum[1];
            //unset($this->enum);
        }
    }

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

        if ($value === $this->valueTrue) {
            $value = true;
        } elseif ($value === $this->valueFalse) {
            $value = false;
        } elseif (is_numeric($value)) {
            $value = (bool) $value;
        }

        if (!is_bool($value)) {
            throw new ValidationException([$this->name => 'Must be a boolean value']);
        }

        // if value required, then only valueTrue is allowed
        if ($this->required && $value !== true) {
            throw new ValidationException([$this->name => 'Must be selected']);
        }

        return $value;
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     *
     * @return string
     */
    public function toString($value = null)
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v === true ? '1' : '0';
    }
}
