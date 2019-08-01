<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Array field type. Think of it as field type "array" in past.
 */
class Array_ extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'array';

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

        if (is_string($value) && $this->owner && $this->owner->persistence) {
            $value = $this->owner->persistence->jsonDecode($this, $value, true);
        }

        if (!is_array($value)) {
            throw new ValidationException([$this->name => 'Must be an array']);
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
    public function toString($value = null) : ?string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        if ($this->owner && $this->owner->persistence) {
            $v = $this->owner->persistence->jsonEncode($this, $v);
        } else {
            $v = json_encode($v);
        }

        return $v;
    }
}
