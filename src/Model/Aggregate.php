<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Reference;

/**
 * Aggregate model allows you to query using "group by" clause on your existing model.
 * It's quite simple to set up.
 *
 * $aggregate = new Aggregate($mymodel);
 * $aggregate->groupBy(['first','last'], ['salary'=>'sum([])'];
 *
 * your resulting model will have 3 fields:
 *  first, last, salary
 *
 * but when querying it will use the original model to calculate the query, then add grouping and aggregates.
 *
 * If you wish you can add more fields, which will be passed through:
 * $aggregate->addField('middle');
 *
 * If this field exist in the original model it will be added and you'll get exception otherwise. Finally you are
 * permitted to add expressions.
 *
 * The base model must not be Union model or another Aggregate model, however it's possible to use Aggregate model as nestedModel inside Union model.
 * Union model implements identical grouping rule on its own.
 *
 * You can also pass seed (for example field type) when aggregating:
 * $aggregate->groupBy(['first', 'last'], ['salary' => ['sum([])', 'type' => 'atk4_money']];
 *
 * @property \Atk4\Data\Persistence\Sql $persistence
 *
 * @method Expression expr($expr, array $args = []) forwards to Persistence\Sql::expr using $this as model
 */
class Aggregate extends Model
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';

    /** @var Model */
    public $baseModel;

    /** @var array<int, string|Expression> */
    public $groupByFields = [];

    /** @var array<string, array|object> */
    public $aggregateExpressions = [];

    public function __construct(Model $baseModel, array $defaults = [])
    {
        if (!$baseModel->persistence instanceof Persistence\Sql) {
            throw new Exception('Base model must have Sql persistence to use grouping');
        }

        $this->baseModel = clone $baseModel;
        $this->table = $baseModel->table;

        // this model does not have ID field
        $this->id_field = null;

        // this model should always be read-only
        $this->read_only = true;

        parent::__construct($baseModel->persistence, $defaults);
    }

    /**
     * @return $this
     */
    public function groupBy(array $fields, array $aggregateExpressions = []): Model
    {
        $this->groupByFields = array_unique(array_merge($this->groupByFields, $fields));

        foreach ($fields as $fieldName) {
            if ($fieldName instanceof Expression) {
                continue;
            }

            $this->addField($fieldName);
        }

        foreach ($aggregateExpressions as $name => $seed) {
            $this->aggregateExpressions[$name] = $seed;

            $args = [];
            // if field originally defined in the parent model, then it can be used as part of expression
            if ($this->baseModel->hasField($name)) {
                $args = [$this->baseModel->getField($name)];
            }

            $seed[0 /* TODO 'expr' was here, 0 fixes tests, but 'expr' in seed might this be defined */] = $this->baseModel->expr($seed[0] ?? $seed['expr'], $args);

            // now add the expressions here
            $this->addExpression($name, $seed);
        }

        return $this;
    }

    public function getRef(string $link): Reference
    {
        return $this->baseModel->getRef($link);
    }

    /**
     * @return $this
     */
    public function withAggregateField(string $name, $seed = []): Model
    {
        static::addField($name, $seed);

        return $this;
    }

    /**
     * Adds new field into model.
     *
     * @param array|object $seed
     */
    public function addField(string $name, $seed = []): Field
    {
        if ($seed instanceof SqlExpressionField) {
            return parent::addField($name, $seed);
        }

        if ($seed['never_persist'] ?? false) {
            return parent::addField($name, $seed);
        }

        if ($this->baseModel->hasField($name)) {
            $field = clone $this->baseModel->getField($name);
            $field->unsetOwner(); // will be new owner
        } else {
            $field = null;
        }

        return $field
            ? parent::addField($name, $field)->setDefaults($seed)
            : parent::addField($name, $seed);
    }

    /**
     * @param string $mode
     * @param array  $args
     *
     * @return Query
     */
    public function action($mode, $args = [])
    {
        switch ($mode) {
            case 'select':
                $fields = $this->onlyFields ?: array_keys($this->getFields());

                // select but no need your fields
                $query = $this->baseModel->action($mode, [false]);

                $this->initQueryFields($query, array_unique($fields + $this->groupByFields));
                $this->initQueryOrder($query);
                $this->initQueryGrouping($query);
                $this->initQueryConditions($query);
                $this->initQueryLimit($query);

                $this->hook(self::HOOK_INIT_SELECT_QUERY, [$query]);

                return $query;
            case 'count':
                $query = $this->baseModel->action($mode, $args);

                $query->reset('field')->field($this->expr('1'));
                $this->initQueryGrouping($query);

                $this->hook(self::HOOK_INIT_SELECT_QUERY, [$query]);

                return $query->dsql()->field('count(*)')->table($this->expr('([]) der', [$query]));
            case 'field':
            case 'fx':
                return parent::action($mode, $args);
            default:
                throw (new Exception('Aggregate model does not support this action'))
                    ->addMoreInfo('mode', $mode);
        }
    }

    protected function initQueryFields(Query $query, array $fields = []): void
    {
        $this->persistence->initQueryFields($this, $query, $fields);
    }

    protected function initQueryOrder(Query $query): void
    {
        if ($this->order) {
            foreach ($this->order as $order) {
                $isDesc = strtolower($order[1]) === 'desc';

                if ($order[0] instanceof Expressionable) {
                    $query->order($order[0], $isDesc);
                } elseif (is_string($order[0])) {
                    $query->order($this->getField($order[0]), $isDesc);
                } else {
                    throw (new Exception('Unsupported order parameter'))
                        ->addMoreInfo('model', $this)
                        ->addMoreInfo('field', $order[0]);
                }
            }
        }
    }

    protected function initQueryGrouping(Query $query): void
    {
        // use table alias of base model
        $this->table_alias = $this->baseModel->table_alias;

        foreach ($this->groupByFields as $field) {
            if ($field instanceof Expression) {
                $expression = $field;
            } else {
                $expression = $this->baseModel->getField($field)->short_name /* TODO short_name should be used by DSQL automatically when in GROUP BY, HAVING, ... */;
            }

            $query->group($expression);
        }
    }

    protected function initQueryConditions(Query $query, Model\Scope\AbstractScope $condition = null): void
    {
        $condition ??= $this->scope();

        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                $query->having(...$condition->toQueryArguments());
            }

            // nested conditions
            if ($condition instanceof Model\Scope) {
                $expression = $condition->isOr() ? $query->orExpr() : $query->andExpr();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    $this->initQueryConditions($expression, $nestedCondition);
                }

                $query->having($expression);
            }
        }
    }

    protected function initQueryLimit(Query $query): void
    {
        if ($this->limit && ($this->limit[0] || $this->limit[1])) {
            if ($this->limit[0] === null) {
                $this->limit[0] = \PHP_INT_MAX;
            }

            $query->limit($this->limit[0], $this->limit[1]);
        }
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        return array_merge(parent::__debugInfo(), [
            'groupByFields' => $this->groupByFields,
            'aggregateExpressions' => $this->aggregateExpressions,
            'baseModel' => $this->baseModel->__debugInfo(),
        ]);
    }

    // }}}
}
