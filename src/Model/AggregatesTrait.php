<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\data\Model;

/**
 * Provides aggregation methods.
 */
trait AggregatesTrait
{
    /**
     * @see Aggregate::withAggregateField.
     */
    public function withAggregateField($name, $seed = []): Model
    {
        return (new Aggregate($this))->withAggregateField(...func_get_args());
    }

    /**
     * @see Aggregate::groupBy.
     */
    public function groupBy(array $group, array $aggregate = []): Model
    {
        return (new Aggregate($this))->groupBy(...func_get_args());
    }
}
