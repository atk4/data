<?php

declare(strict_types=1);

namespace atk4\data\Model;

/**
 * Provides aggregation methods.
 */
trait AggregatesTrait
{
    /**
     * @see Aggregate::withAggregateField.
     *
     * @return \atk4\data\Model
     */
    public function withAggregateField($name, $seed = [])
    {
        return (new Aggregate($this))->withAggregateField(...func_get_args());
    }

    /**
     * @see Aggregate::groupBy.
     *
     * @return \atk4\data\Model
     */
    public function groupBy(array $group, array $aggregate = [])
    {
        return (new Aggregate($this))->groupBy(...func_get_args());
    }
}
