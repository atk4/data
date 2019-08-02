<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Basic textual field type. Think of it as field type "text" in past.
 * This allows you to have multiple paragrahs (allow line-ends).
 */
class Text extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'text';

    /**
     * @var int specify a maximum length for this text.
     */
    public $max_length;

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
        if ($value === null) {
            if ($this->mandatory || $this->required) {
                throw new ValidationException([$this->name => 'Must not be null']);
            }

            return;
        }

        if (!is_scalar($value)) {
            throw new ValidationException([$this->name => 'Must use scalar value']);
        }

        // normalize line-ends to LF and trim
        $value = trim(str_replace(["\r\n", "\r"], "\n", $value));

        if ($value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be empty']);
            }
        }

        if ($this->max_length && mb_strlen($value) > $this->max_length) {
            throw new ValidationException([$this->name => 'Must be not longer than '.$this->max_length.' symbols']);
        }

        return $value;
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
            'max_length' => null,
        ];

        foreach ($properties as $k=>$v) {
            if ($this->{$k} !== $v) {
                $seed[$k] = $this->{$k};
            }
        }

        return $seed;
    }
}
