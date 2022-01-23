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
     * Specify a single field or array of fields on which we will group model.
     *
     * @param array<string, array|object> $aggregateExpressions Array of aggregate expressions with alias as key
     *
     * @return Aggregate
     *
     * @see Aggregate::groupBy
     */
    public function groupBy(array $fields, array $aggregateExpressions = []): Model
    {
        return (new Aggregate($this))->groupBy($fields, $aggregateExpressions);
    }
}
