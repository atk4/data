<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Object field type. Think of it as field type "object" in past.
 */
class Object_ extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'object';

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
            $value = $this->owner->persistence->jsonDecode($this, $value, FALSE);
        }

        if (!is_object($value)) {
            throw new ValidationException([$this->name => 'Must be an object']);
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

        if ($this->owner && $this->owner->persistence) {
            $v = $this->owner->persistence->jsonEncode($this, $v);
        } else {
            $v = json_encode($v);
        }

        return $v;
    }
}
