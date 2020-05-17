<?php

namespace atk4\data\Persistence;

use atk4\data\Field;

/**
 * Array persistence which will always typecast all values to strings.
 */
class ArrayOfStrings extends Array_
{
    /**
     * Typecast all values to strings when saving.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastSaveField(Field $f, $value)
    {
        return $f->toString($value);
    }

    /**
     * Typecast all values from string to correct ones while loading.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function _typecastLoadField(Field $f, $value)
    {
        // LOB fields return resource stream
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return $f->normalize($value);
    }
}
