<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\MaterializedField;
use Atk4\Data\Persistence\Sql\Query;

/**
 * AggregateModel model allows you to query using "group by" clause on your existing model.
 * It's quite simple to set up.
 *
 * $aggregate = new AggregateModel($mymodel);
 * $aggregate->setGroupBy(['first', 'last'], [
 *     'salary' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
 * ];
 *
 * your resulting model will have 3 fields: first, last, salary
 *
 * but when querying it will use the original model to calculate the query, then add grouping and aggregates.
 *
 * If you wish you can add more fields, which will be passed through:
 * $aggregate->addField('middle');
 *
 * If this field exist in the original model it will be added and you'll get exception otherwise. Finally you are
 * permitted to add expressions.
 *
 * @property Model $table
 *
 * @method Persistence\Sql getPersistence()
 * @method Expression      expr(string $template, array<int|string, mixed> $arguments = []) forwards to Persistence\Sql::expr using $this as model
 */
class AggregateModel extends Model
{
    public const HOOK_INIT_AGGREGATE_SELECT_QUERY = self::class . '@initAggregateSelectQuery';

    /** @var array<int, string|Expression> */
    public $groupByFields = [];

    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(Model $baseModel, array $defaults = [])
    {
        if (!$baseModel->issetPersistence() && !$baseModel->getPersistence() instanceof Persistence\Sql) {
            throw new Exception('Base model must have Sql persistence to use grouping');
        }

        $this->table = $baseModel;

        // this model should always be read-only and does not have ID field
        $this->readOnly = true;
        $this->idField = false;

        parent::__construct($baseModel->getPersistence(), $defaults);
    }

    /**
     * Specify a single field or array of fields on which we will group model. Multiple calls are allowed.
     *
     * @param array<int, string|Expression>      $fields
     * @param array<string, array<mixed>|object> $aggregateExpressions Array of aggregate expressions with alias as key
     *
     * @return $this
     */
    public function setGroupBy(array $fields, array $aggregateExpressions = [])
    {
        $this->groupByFields = array_unique(array_merge($this->groupByFields, $fields));

        foreach ($fields as $fieldName) {
            if ($fieldName instanceof Expression || $this->hasField($fieldName)) {
                continue;
            }

            $this->addField($fieldName);
        }

        foreach ($aggregateExpressions as $name => $seed) {
            $exprArgs = [];
            // if field is defined in the parent model then it can be used in expression
            if ($this->table->hasField($name)) {
                $exprArgs = [$this->table->getField($name)];
            }

            $seed['expr'] = $this->table->expr($seed['expr'], $exprArgs);

            // convert base model fields to aliases, they are always already materialized as the base model is SQL inner table
            foreach ($seed['expr']->args['custom'] as $argK => $argV) {
                $seed['expr']->args['custom'][$argK] = new MaterializedField($this->table, $argV);
            }

            $this->addExpression($name, $seed);
        }

        return $this;
    }

    public function addField(string $name, $seed = []): Field
    {
        if ($seed instanceof SqlExpressionField) {
            return parent::addField($name, $seed);
        }

        if ($this->table->hasField($name)) {
            $innerField = $this->table->getField($name);
            $seed['type'] ??= $innerField->type;
            $seed['enum'] ??= $innerField->enum;
            $seed['values'] ??= $innerField->values;
            $seed['caption'] ??= $innerField->caption;
            $seed['ui'] ??= $innerField->ui;
        }

        return parent::addField($name, $seed);
    }

    public function action(string $mode, array $args = [])
    {
        switch ($mode) {
            case 'select':
                $fields = $args[0] ?? array_unique(array_merge(
                    $this->onlyFields ?? array_keys($this->getFields()),
                    array_filter($this->groupByFields, static fn ($v) => !$v instanceof Expression)
                ));

                $query = parent::action($mode, [[]]);
                if (isset($query->args['where'])) {
                    $query->args['having'] = $query->args['where'];
                    unset($query->args['where']);
                }

                $this->getPersistence()->initQueryFields($this, $query, $fields);
                $this->initQueryGrouping($query);

                $this->hook(self::HOOK_INIT_AGGREGATE_SELECT_QUERY, [$query]);

                return $query;
            case 'count':
                $innerQuery = $this->action('select', [[]]);
                $innerQuery->reset('field')->field($this->expr('1'));

                $query = $innerQuery->dsql()
                    ->field('count(*)', $args['alias'] ?? null)
                    ->table($this->expr('([]) {}', [$innerQuery, '_tc']));

                return $query;
            case 'field':
            case 'fx':
            case 'fx0':
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
                $expression = new MaterializedField($this->table, $this->table->getField($field));
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
