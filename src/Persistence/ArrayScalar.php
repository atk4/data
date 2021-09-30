<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Field;

/**
 * Scalar persistence which will always typecast all values to scalars.
 */
class ArrayScalar extends Array_
{
    protected function _typecastSaveField(Field $field, $value)
    {
        $value = parent::_typecastSaveField($field, $value);

        return $value === null || is_scalar($value) ? $value : (string) $value;
    }
}
