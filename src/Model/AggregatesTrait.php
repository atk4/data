<?php

declare(strict_types=1);

namespace atk4\data\Model;

/**
 * Provides native Model methods for join functionality.
 */
trait AggregatesTrait
{
    /**
     * Sum the values of the field.
     */
    public function getSum($field, bool $coalesce = false)
    {
        return $this->toQuery()->aggregate('sum', $field, null, $coalesce)->getOne();
    }

    /**
     * Get the average of all field values.
     */
    public function getAvg($field, bool $coalesce = false)
    {
        return $this->toQuery()->aggregate('avg', $field, null, $coalesce)->getOne();
    }

    /**
     * Get the max of all field values.
     */
    public function getMax($field)
    {
        return $this->toQuery()->aggregate('max', $field)->getOne();
    }

    /**
     * Get the min of all field values.
     */
    public function getMin($field)
    {
        return $this->toQuery()->aggregate('min', $field)->getOne();
    }
}
