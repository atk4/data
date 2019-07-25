<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Basic string field type. Think of it as field type "string" in past.
 */
class ShortText extends Text
{
    /** @var string Field type for backward compatibility. */
    public $type = 'string';

    /**
     * @var int specify a maximum length for this text.
     */
    public $max_length = 255;

    /**
     * Normalize value to string.
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

        $value = trim(str_replace(["\r", "\n"], '', $value));

        return $value;
    }
}
