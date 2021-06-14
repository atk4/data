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
     * @see Aggregate::withAggregateField.
     */
    public function withAggregateField(string $name, $seed = []): Model
    {
        return (new Aggregate($this))->withAggregateField($name, $seed);
    }

    /**
     * @see Aggregate::groupBy.
     */
    public function groupBy(array $fields, array $aggregateExpressions = [])
    {
        return (new Aggregate($this))->groupBy($fields, $aggregateExpressions);
    }
}
