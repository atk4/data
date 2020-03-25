<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\ValidationException;

/**
 * Your favorite nullable binary type.
 */
class Boolean extends \atk4\data\Field
{
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

    protected static $seedProperties = [
        'valueTrue',
        'valueFalse',
    ];

    /**
     * Constructor.
     */
    public function init()
    {
        parent::init();

        // Backwards compatibility
        if ($this->enum) {
            $this->valueFalse = $this->enum[0];
            $this->valueTrue = $this->enum[1];
            //unset($this->enum);
        }
    }

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
        if (is_null($value) || $value === '') {
            return;
        }
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
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     *
     * @return string
     */
    public function toString($value = null): ?string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v === true ? '1' : '0';
    }
}
