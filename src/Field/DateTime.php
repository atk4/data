<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;
use atk4\data\ValidationException;

/**
 * Basic datetime field type. Think of it as field type "datetime" in past.
 */
class DateTime extends Field
{
    /** @var string Field type for backward compatibility. */
    public $type = 'datetime';

    /**
     * Array with Persistence settings like format, timezone etc.
     * It's job of Persistence to take these settings into account if needed.
     *
     * @var array
     */
    public $persistence = [
        'format' => null, // for date it can be 'Y-m-d', for datetime - 'Y-m-d H:i:s' etc.
        'timezone' => 'UTC', // 'IST', 'UTC', 'Europe/Riga' etc.
    ];

    /**
     * DateTime class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTime', 'Carbon' etc.
     *
     * @param string
     */
    public $dateTimeClass = 'DateTime';

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon' etc.
     *
     * @param string
     */
    public $dateTimeZoneClass = 'DateTimeZone';

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

            return null;
        }

        if ($value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be empty']);
            }
            return null;
        }

        // we allow http://php.net/manual/en/datetime.formats.relative.php
        $class = $this->dateTimeClass ?? 'DateTime';

        if (is_numeric($value)) {
            $value = new $class('@'.$value);
        } elseif (is_string($value)) {
            $value = new $class($value);
        } elseif (!$value instanceof $class) {
            if (is_object($value)) {
                throw new ValidationException(['must be a '.$this->type, 'class' => $class, 'value class' => get_class($value)]);
            }

            throw new ValidationException(['must be a '.$this->type, 'class' => $class, 'value type' => gettype($value)]);
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

        return $v->format('c'); // ISO 8601 format 2004-02-12T15:19:21+00:00
    }
}
