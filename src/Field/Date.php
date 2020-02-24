<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

/**
 * Date field type. Think of it as field type "date" in past.
 */
class Date extends DateTime
{
    /** @var string Field type for backward compatibility. */
    public $type = 'date';

    /**
     * Validate and normalize value.
     *
     * @param mixed $value
     *
     * @throws \atk4\data\ValidationException
     *
     * @return mixed
     */
    public function normalize($value)
    {
        $value = parent::normalize($value);

        if ($value !== null) {
            // remove time portion from date type value
            $value->setTime(0, 0, 0);
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
    public function toString($value = null): ?string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $v ? $v->format('Y-m-d') : $v;
    }
}
