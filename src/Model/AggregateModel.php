<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Reference;

/**
 * AggregateModel model allows you to query using "group by" clause on your existing model.
 * It's quite simple to set up.
 *
 * $aggregate = new AggregateModel($mymodel);
 * $aggregate->setGroupBy(['first','last'], ['salary'=>'sum([])'];
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
 * You can also pass seed (for example field type) when aggregating:
 * $aggregate->setGroupBy(['first', 'last'], ['salary' => ['sum([])', 'type' => 'atk4_money']];
 *
 * @property Persistence\Sql $persistence
 * @property Model           $table
 *
 * @method Expression expr($expr, array $args = []) forwards to Persistence\Sql::expr using $this as model
 */
class AggregateModel extends Model
{
    /** @const string */
    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';

    /** @var array<int, string|Expression> */
    public $groupByFields = [];

    public function __construct(Model $baseModel, array $defaults = [])
    {
        if (!$baseModel->persistence instanceof Persistence\Sql) {
            throw new Exception('Base model must have Sql persistence to use grouping');
        }

        $this->table = $baseModel;

        // this model does not have ID field
        $this->id_field = null;

        // this model should always be read-only
        $this->read_only = true;

        parent::__construct($baseModel->persistence, $defaults);
    }

    /**
     * Specify a single field or array of fields on which we will group model. Multiple calls are allowed.
     *
     * @param array<string, array|object> $aggregateExpressions Array of aggregate expressions with alias as key
     *
     * @return $this
     */
    public function setGroupBy(array $fields, array $aggregateExpressions = [])
    {
        $this->groupByFields = array_unique(array_merge($this->groupByFields, $fields));

        foreach ($fields as $fieldName) {
            if ($fieldName instanceof Expression) {
                continue;
            }

            $this->addField($fieldName);
        }

        foreach ($aggregateExpressions as $name => $seed) {
            $args = [];
            // if field originally defined in the parent model, then it can be used as part of expression
            if ($this->table->hasField($name)) {
                $args = [$this->table->getField($name)];
            }

            $seed[0 /* TODO 'expr' was here, 0 fixes tests, but 'expr' in seed might this be defined */] = $this->table->expr($seed[0] ?? $seed['expr'], $args);

            // now add the expressions here
            $this->addExpression($name, $seed);
        }

        return $this;
    }

    /**
     * TODO this should be removed, nasty hack to pass the tests.
     */
    public function getRef(string $link): Reference
    {
        $ref = clone $this->table->getRef($link);
        $ref->unsetOwner();
        $ref->setOwner($this);

        return $ref;
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

        if ($this->table->hasField($name)) {
            $field = clone $this->table->getField($name);
            $field->unsetOwner();
        } else {
            $field = null;
        }

        return $field
            ? parent::addField($name, $field)->setDefaults($seed)
            : parent::addField($name, $seed);
    }

    /**
     * @return Query
     */
    public function action(string $mode, array $args = [])
    {
        switch ($mode) {
            case 'select':
                $fields = $this->onlyFields ?: array_keys($this->getFields());

                // select but no need your fields
                $query = parent::action($mode, [false]);
                if (isset($query->args['where'])) {
                    $query->args['having'] = $query->args['where'];
                    unset($query->args['where']);
                }

                $this->persistence->initQueryFields($this, $query, array_unique($fields + $this->groupByFields));
                $this->initQueryGrouping($query);

                $this->hook(self::HOOK_INIT_SELECT_QUERY, [$query]);

                return $query;
            case 'count':
                $query = parent::action($mode, $args);
                if (isset($query->args['where'])) {
                    $query->args['having'] = $query->args['where'];
                    unset($query->args['where']);
                }

                $query->reset('field')->field($this->expr('1'));
                $this->initQueryGrouping($query);

                $this->hook(self::HOOK_INIT_SELECT_QUERY, [$query]);

                return $query->dsql()->field('count(*)')->table($this->expr('([]) {}', [$query, '_tc']));
            case 'field':
            case 'fx':
                return parent::action($mode, $args);
            default:
                throw (new Exception('AggregateModel model does not support this action'))
                    ->addMoreInfo('mode', $mode);
        }
    }

    protected function initQueryGrouping(Query $query): void
    {
        foreach ($this->groupByFields as $field) {
            if ($field instanceof Expression) {
                $expression = $field;
            } else {
                $expression = $this->table->getField($field)->short_name /* TODO short_name should be used by DSQL automatically when in GROUP BY, HAVING, ... */;
            }

            $query->group($expression);
        }
    }

    public function __debugInfo(): array
    {
        return array_merge(parent::__debugInfo(), [
            'groupByFields' => $this->groupByFields,
        ]);
    }
}
