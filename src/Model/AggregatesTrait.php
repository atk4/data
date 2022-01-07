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
     * Method to enable commutative usage of methods enabling both of below
     * Resulting in Aggregate on $model.
     *
     * $model->groupBy(['abc'])->withAggregateField('xyz');
     *
     * and
     *
     * $model->withAggregateField('xyz')->groupBy(['abc']);
     *
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
