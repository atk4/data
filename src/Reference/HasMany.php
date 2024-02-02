<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Core\Factory;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;

class HasMany extends Reference
{
    private function getModelTableString(Model $model): string
    {
        if (is_object($model->table)) {
            return $this->getModelTableString($model->table);
        }

        return $model->table;
    }

    #[\Override]
    public function getTheirFieldName(Model $theirModel = null): string
    {
        if ($this->theirField) {
            return $this->theirField;
        }

        // this is pure guess, verify if such field exist, otherwise throw
        // TODO probably remove completely in the future
        $ourModel = $this->getOurModel(null);
        $theirFieldName = preg_replace('~^.+\.~s', '', $this->getModelTableString($ourModel)) . '_' . $ourModel->idField;
        if (!($theirModel ?? $this->createTheirModel())->hasField($theirFieldName)) {
            throw (new Exception('Their model does not contain fallback field'))
                ->addMoreInfo('their_fallback_field', $theirFieldName);
        }

        return $theirFieldName;
    }

    /**
     * Returns our field value or id.
     *
     * @return mixed
     */
    protected function getOurFieldValueForRefCondition(Model $ourModelOrEntity)
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        if ($ourModelOrEntity->isEntity()) {
            $res = $this->ourField
                ? $ourModelOrEntity->get($this->ourField)
                : $ourModelOrEntity->getId();
            $this->assertReferenceValueNotNull($res);

            return $res;
        }

        // create expression based on existing conditions
        return $ourModelOrEntity->action('field', [
            $this->getOurFieldName(),
        ]);
    }

    /**
     * Returns our field or id field.
     */
    protected function referenceOurValue(): Field
    {
        // TODO horrible hack to render the field with a table prefix,
        // find a solution how to wrap the field inside custom Field (without owner?)
        $ourModelCloned = clone $this->getOurModel(null);
        $ourModelCloned->persistenceData['use_table_prefixes'] = true;

        return $ourModelCloned->getReference($this->link)->getOurField();
    }

    /**
     * Returns referenced model with condition set.
     */
    #[\Override]
    public function ref(Model $ourModelOrEntity, array $defaults = []): Model
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        return $this->createTheirModel($defaults)->addCondition(
            $this->getTheirFieldName(),
            $this->getOurFieldValueForRefCondition($ourModelOrEntity)
        );
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     *
     * @param array<string, mixed> $defaults
     */
    public function refLink(?Model $ourModel, array $defaults = []): Model
    {
        $this->getOurModel($ourModel); // or should $this->assertOurModelOrEntity($ourModelOrEntity); be here? What is exactly the difference between ref and refLink?

        $theirModelLinked = $this->createTheirModel($defaults)->addCondition(
            $this->getTheirFieldName(),
            $this->referenceOurValue()
        );

        return $theirModelLinked;
    }

    /**
     * Adds field as expression to our model. Used in aggregate strategy.
     *
     * @param array<string, mixed> $defaults
     */
    public function addField(string $fieldName, array $defaults = []): Field
    {
        if (!isset($defaults['aggregate']) && !isset($defaults['concat']) && !isset($defaults['expr'])) {
            throw (new Exception('Aggregate field requires "aggregate", "concat" or "expr" specified to hasMany()->addField()'))
                ->addMoreInfo('field', $fieldName)
                ->addMoreInfo('defaults', $defaults);
        }

        $defaults['aggregateRelation'] = $this;

        $alias = $defaults['field'] ?? null;
        $field = $alias ?? $fieldName;

        if (isset($defaults['concat'])) {
            $defaults['aggregate'] = 'concat';
            $defaults['concatSeparator'] = $defaults['concat'];
            unset($defaults['concat']);
        }

        if (isset($defaults['expr'])) {
            $fx = function () use ($defaults, $alias) {
                $theirModelLinked = $this->refLink(null);

                return $theirModelLinked->action('field', [$theirModelLinked->expr(
                    $defaults['expr'],
                    $defaults['args'] ?? []
                ), 'alias' => $alias]);
            };
            unset($defaults['args']);
        } elseif (is_object($defaults['aggregate'])) {
            $fx = function () use ($defaults, $alias) {
                return $this->refLink(null)->action('field', [$defaults['aggregate'], 'alias' => $alias]);
            };
        } elseif ($defaults['aggregate'] === 'count' && !isset($defaults['field'])) {
            $fx = function () use ($alias) {
                return $this->refLink(null)->action('count', ['alias' => $alias]);
            };
        } elseif (in_array($defaults['aggregate'], ['sum', 'avg', 'min', 'max', 'count'], true)) {
            $fx = function () use ($defaults, $field) {
                return $this->refLink(null)->action('fx0', [$defaults['aggregate'], $field]);
            };
        } else {
            $fx = function () use ($defaults, $field) {
                $args = [$defaults['aggregate'], $field];
                if ($defaults['aggregate'] === 'concat') {
                    $args['concatSeparator'] = $defaults['concatSeparator'];
                }

                return $this->refLink(null)->action('fx', $args);
            };
        }

        return $this->getOurModel(null)->addExpression($fieldName, array_merge($defaults, ['expr' => $fx]));
    }

    /**
     * Adds multiple fields.
     *
     * @param array<string, array<mixed>>|array<int, string> $fields
     * @param array<mixed>                                   $defaults
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $defaults = [])
    {
        foreach ($fields as $k => $v) {
            if (is_int($k)) {
                $k = $v;
                $v = [];
            }

            $this->addField($k, Factory::mergeSeeds($v, $defaults));
        }

        return $this;
    }
}
