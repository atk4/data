<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\FieldSqlExpression;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Reference;
use Atk4\Dsql\Expression;
use Atk4\Dsql\Query;

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
 * $aggregate->groupBy(['first','last'], ['salary' => ['sum([])', 'type'=>'money']];
 *
 * @property \Atk4\Data\Persistence\Sql $persistence
 *
 * @method Expression expr($expr, array $args = []) forwards to Persistence\Sql::expr using $this as model
 */
class Aggregate extends Model
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';

    /**
     * @deprecated use HOOK_INIT_SELECT_QUERY instead - will be removed dec-2020
     */
    public const HOOK_AFTER_GROUP_SELECT = self::HOOK_INIT_SELECT_QUERY;

    /** @var array */
    protected $systemFields = [];

    /** @var Model */
    public $baseModel;

    /**
     * Aggregate model should always be read-only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * Aggregate does not have ID field.
     *
     * @var string
     */
    public $id_field;

    /** @var array */
    public $group = [];

    /** @var array */
    public $aggregate = [];

    /**
     * Constructor.
     */
    public function __construct(Model $baseModel, array $defaults = [])
    {
        if (!$baseModel->persistence instanceof Persistence\Sql) {
            throw new Exception('Base model must have Sql persistence to use grouping');
        }

        $this->baseModel = clone $baseModel;
        $this->table = $baseModel->table;

        parent::__construct($baseModel->persistence, $defaults);

        // always use table prefixes for this model
        $this->persistence_data['use_table_prefixes'] = true;
    }

    /**
     * Specify a single field or array of fields on which we will group model.
     *
     * @param array $fields    Array of field names
     * @param array $aggregate Array of aggregate mapping
     *
     * @return $this
     */
    public function groupBy(array $fields, array $aggregate = []): Model
    {
        $this->group = $fields;
        $this->aggregate = $aggregate;

        $this->systemFields = array_unique($this->systemFields + $fields);
        foreach ($fields as $fieldName) {
            $this->addField($fieldName);
        }

        foreach ($aggregate as $fieldName => $expr) {
            $seed = is_array($expr) ? $expr : [$expr];

            $args = [];
            // if field originally defined in the parent model, then it can be used as part of expression
            if ($this->baseModel->hasField($fieldName)) {
                $args = [$this->baseModel->getField($fieldName)];
            }

            $seed['expr'] = $this->baseModel->expr($seed[0] ?? $seed['expr'], $args);

            // now add the expressions here
            $this->addExpression($fieldName, $seed);
        }

        return $this;
    }

    /**
     * Return reference field.
     *
     * @param string $link
     */
    public function getRef($link): Reference
    {
        return $this->baseModel->getRef($link);
    }

    /**
     * Method to enable commutative usage of methods enabling both of below
     * Resulting in Aggregate on $model.
     *
     * $model->groupBy(['abc'])->withAggregateField('xyz');
     *
     * and
     *
     * $model->withAggregateField('xyz')->groupBy(['abc']);
     */
    public function withAggregateField($name, $seed = []): Model
    {
        static::addField(...func_get_args());

        return $this;
    }

    /**
     * Adds new field into model.
     *
     * @param array|object $seed
     */
    public function addField(string $name, $seed = []): Field
    {
        $seed = is_array($seed) ? $seed : [$seed];

        if (isset($seed[0]) && $seed[0] instanceof FieldSqlExpression) {
            return parent::addField($name, $seed[0]);
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
     * Given a query, will add safe fields in.
     */
    public function initQueryFields(Query $query, array $fields = []): Query
    {
        $this->persistence->initQueryFields($this, $query, $fields);

        return $query;
    }

    /**
     * Adds grouping in query.
     */
    public function initQueryGrouping(Query $query)
    {
        // use table alias of base model
        $this->table_alias = $this->baseModel->table_alias;

        foreach ($this->group as $field) {
            if ($this->baseModel->hasField($field)) {
                $expression = $this->baseModel->getField($field);
            } else {
                $expression = $this->expr($field);
            }

            $query->group($expression);
        }
    }

    public function setLimit(int $count = null, int $offset = 0)
    {
        $this->baseModel->setLimit($count, $offset);

        return $this;
    }

    /**
     * Execute action.
     *
     * @param string $mode
     * @param array  $args
     *
     * @return Query
     */
    public function action($mode, $args = [])
    {
        switch ($mode) {
            case 'select':
                $fields = $this->only_fields ?: array_keys($this->getFields());

                // select but no need your fields
                $query = $this->baseModel->action($mode, [false]);
                $this->initQueryFields($query, array_unique($fields + $this->systemFields));

                $this->initQueryOrder($query);
                $this->initQueryGrouping($query);
                $this->initQueryConditions($query);

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

    protected function initQueryOrder(Query $query)
    {
        if ($this->order) {
            foreach ($this->order as $order) {
                $isDesc = strtolower($order[1]) === 'desc';

                if ($order[0] instanceof Expression) {
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

    /**
     * Our own way applying conditions, where we use "having" for fields.
     */
    public function initQueryConditions(Query $query, Model\Scope\AbstractScope $condition = null): void
    {
        $condition = $condition ?? $this->scope();

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

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        return array_merge(parent::__debugInfo(), [
            'group' => $this->group,
            'aggregate' => $this->aggregate,
            'baseModel' => $this->baseModel->__debugInfo(),
        ]);
    }

    // }}}
}
