<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Model;

/**
 * Provides aggregation methods.
 */
trait AggregatesTrait
{
    /**
     * @param array|object $seed
     *
     * @return Aggregate
     *
     * @see Aggregate::withAggregateField.
     */
    public function withAggregateField(string $name, $seed = []): Model
    {
        return (new Aggregate($this))->withAggregateField($name, $seed);
    }

    /**
     * @return Aggregate
     *
     * @see Aggregate::groupBy.
     */
    public function groupBy(array $fields, array $aggregateExpressions = []): Model
    {
        return (new Aggregate($this))->groupBy($fields, $aggregateExpressions);
    }
}
