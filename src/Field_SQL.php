<?php

declare(strict_types=1);

namespace atk4\data;

use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

/**
 * Class description?
 *
 * @property Join\SQL $join
 */
class Field_SQL extends Field implements Expressionable
{
    /**
     * SQL fields are allowed to have expressions inside of them.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if ($value instanceof Expression ||
            $value instanceof Expressionable) {
            return $value;
        }

        return parent::normalize($value);
    }
}
