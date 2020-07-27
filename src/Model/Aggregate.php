<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\data\Model;
use atk4\data\Reference;
use atk4\dsql\Query;

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
 */
class Aggregate extends Model
{
    /** @const string */
    public const HOOK_AFTER_SELECT = self::class . '@afterSelect';

    /**
     * @deprecated use HOOK_AFTER_SELECT instead - will be removed dec-2020
     */
    public const HOOK_AFTER_GROUP_SELECT = self::HOOK_AFTER_SELECT;

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

    /** @var Model */
    public $master_model;

    /** @var array */
    public $group = [];

    /** @var array */
    public $aggregate = [];

    /** @var array */
    public $system_fields = [];

    /**
     * Constructor.
     */
    public function __construct(Model $model, array $defaults = [])
    {
        $this->master_model = $model;
        $this->table = $model->table;

        //$this->_default_class_addExpression = $model->_default_class_addExpression;
        parent::__construct($model->persistence, $defaults);

        // always use table prefixes for this model
        $this->persistence_data['use_table_prefixes'] = true;
    }

    /**
     * Specify a single field or array of fields on which we will group model.
     *
     * @param array $group     Array of field names
     * @param array $aggregate Array of aggregate mapping
     *
     * @return $this
     */
    public function groupBy(array $group, array $aggregate = [])
    {
        $this->group = $group;
        $this->aggregate = $aggregate;

        $this->system_fields = array_unique($this->system_fields + $group);
        foreach ($group as $fieldName) {
            $this->addField($fieldName);
        }

        foreach ($aggregate as $fieldName => $expr) {
            $seed = is_array($expr) ? $expr : [$expr];

            $args = [];
            // if field originally defined in the parent model, then it can be used as part of expression
            if ($this->master_model->hasField($fieldName)) {
                $args = [$this->master_model->getField($fieldName)]; // @TODO Probably need cloning here
            }

            $seed['expr'] = $this->master_model->expr($seed[0] ?? $seed['expr'], $args);

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
        return $this->master_model->getRef($link);
    }

    /**
     * Adds new field into model.
     *
     * @param string       $name
     * @param array|object $seed
     *
     * @return Field
     */
    public function addField($name, $seed = [])
    {
        $seed = is_array($seed) ? $seed : [$seed];

        if (isset($seed[0]) && $seed[0] instanceof FieldSqlExpression) {
            return parent::addField($name, $seed[0]);
        }

        if ($seed['never_persist'] ?? false) {
            return parent::addField($name, $seed);
        }

        if ($this->master_model->hasField($name)) {
            $field = clone $this->master_model->getField($name);
            $field->owner = null; // will be new owner
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
    public function queryFields(Query $query, array $fields = []): Query
    {
        $this->persistence->initQueryFields($this, $query, $fields);

        return $query;
    }

    /**
     * Adds grouping in query.
     */
    public function addGrouping(Query $query)
    {
        // use table alias of master model
        $this->table_alias = $this->master_model->table_alias;

        foreach ($this->group as $field) {
            if ($this->master_model->hasField($field)) {
                $expression = $this->master_model->getField($field);
            } else {
                $expression = $this->expr($field);
            }

            $query->group($expression);
        }
    }

    /**
     * Sets limit.
     *
     * @param int      $count
     * @param int|null $offset
     *
     * @return $this
     *
     * @todo Incorrect implementation
     */
    public function setLimit($count, $offset = null)
    {
        $this->master_model->setLimit($count, $offset);

        return $this;
    }

    /**
     * Sets order.
     *
     * @param mixed     $field
     * @param bool|null $desc
     *
     * @return $this
     *
     * @todo Incorrect implementation
     */
    public function setOrder($field, $desc = null)
    {
        $this->master_model->setOrder($field, $desc);

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
        $subquery = null;
        switch ($mode) {
            case 'select':
                $fields = $this->only_fields ?: array_keys($this->getFields());

                // select but no need your fields
                $query = $this->master_model->action($mode, [false]);
                $query = $this->queryFields($query, array_unique($fields + $this->system_fields));

                $this->addGrouping($query);
                $this->initQueryConditions($query);

                $this->hook(self::HOOK_AFTER_SELECT, [$query]);

                return $query;
            case 'count':
                $query = $this->master_model->action($mode, $args);

                $query->reset('field')->field($this->expr('1'));
                $this->addGrouping($query);

                $this->hook(self::HOOK_AFTER_SELECT, [$query]);

                return $query->dsql()->field('count(*)')->table($this->expr('([]) der', [$query]));
            case 'field':
                if (!isset($args[0])) {
                    throw (new Exception('This action requires one argument with field name'))
                        ->addMoreInfo('mode', $mode);
                }

                if (!is_string($args[0])) {
                    throw (new Exception('action "field" only support string fields'))
                        ->addMoreInfo('field', $args[0]);
                }

                $subquery = $this->getSubQuery([$args[0]]);

                break;
            case 'fx':
                $subquery = $this->getSubAction('fx', [$args[0], $args[1], 'alias' => 'val']);

                $args = [$args[0], $this->expr('val')];

                break;
            default:
                throw (new Exception('Aggregate model does not support this action'))
                    ->addMoreInfo('mode', $mode);
        }

        // Substitute FROM table with our subquery expression
        return parent::action($mode, $args)->reset('table')->table($subquery);
    }

    /**
     * Our own way applying conditions, where we use "having" for
     * fields.
     */
    public function initQueryConditions(Query $query, Model\Scope\AbstractScope $condition = null): void
    {
        $condition = $condition ?? $this->scope();

        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                $query = $query->having(...$condition->toQueryArguments());
            }

            // nested conditions
            if ($condition instanceof Model\Scope) {
                $expression = $condition->isOr() ? $query->orExpr() : $query->andExpr();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    $this->initQueryConditions($expression, $nestedCondition);
                }

                $query = $query->having($expression);
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
            'master_model' => $this->master_model->__debugInfo(),
        ]);
    }

    // }}}
}
