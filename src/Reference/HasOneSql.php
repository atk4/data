<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Exception;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;

class HasOneSql extends HasOne
{
    /**
     * @param ($theirFieldIsTitle is true ? null : string) $theirFieldName
     * @param array<string, mixed>                         $defaults
     */
    private function _addField(string $fieldName, bool $theirFieldIsTitle, ?string $theirFieldName, array $defaults): SqlExpressionField
    {
        $ourModel = $this->getOurModel();

        $fieldExpression = $ourModel->addExpression($fieldName, array_merge([
            'expr' => function (Model $ourModel) use ($theirFieldIsTitle, $theirFieldName) {
                $theirModel = $ourModel->refLink($this->link);
                if ($theirFieldIsTitle) {
                    $theirFieldName = $theirModel->titleField;
                }

                return $theirModel->action('field', [$theirFieldName]);
            },
        ], $defaults, [
            // allow to set our field value by an imported foreign field, but only when the our field value is null
            'readOnly' => false,
        ]));

        $this->onHookToOurModel(Model::HOOK_BEFORE_SAVE, function (Model $ourEntity) use ($fieldName, $theirFieldIsTitle, $theirFieldName) {
            if ($ourEntity->isDirty($fieldName)) {
                $theirModel = $this->createTheirModel();
                if ($theirFieldIsTitle) {
                    $theirFieldName = $theirModel->titleField;
                }

                // when our field is not null or dirty too, update nothing, but check if the imported
                // field was changed to expected value implied by the relation
                if ($ourEntity->isDirty($this->getOurFieldName()) || $ourEntity->get($this->getOurFieldName()) !== null) {
                    $importedFieldValue = $ourEntity->get($fieldName);
                    $expectedTheirEntity = $theirModel->loadBy($this->getTheirFieldName($theirModel), $ourEntity->get($this->getOurFieldName()));
                    if (!$expectedTheirEntity->compare($theirFieldName, $importedFieldValue)) {
                        throw (new Exception('Imported field was changed to an unexpected value'))
                            ->addMoreInfo('ourFieldName', $this->getOurFieldName())
                            ->addMoreInfo('theirFieldName', $this->getTheirFieldName($theirModel))
                            ->addMoreInfo('importedFieldName', $fieldName)
                            ->addMoreInfo('sourceFieldName', $theirFieldName)
                            ->addMoreInfo('importedFieldValue', $importedFieldValue)
                            ->addMoreInfo('sourceFieldValue', $expectedTheirEntity->get($theirFieldName));
                    }
                } else {
                    $newTheirEntity = $theirModel->loadBy($theirFieldName, $ourEntity->get($fieldName));
                    $ourEntity->set($this->getOurFieldName(), $newTheirEntity->get($this->getTheirFieldName($theirModel)));
                    $ourEntity->_unset($fieldName);
                }
            }
        }, [], 20);

        return $fieldExpression;
    }

    /**
     * Creates expression which sub-selects a field inside related model.
     *
     * @param array<string, mixed> $defaults
     */
    public function addField(string $fieldName, ?string $theirFieldName = null, array $defaults = []): SqlExpressionField
    {
        if ($theirFieldName === null) {
            $theirFieldName = $fieldName;
        }

        $ourModel = $this->getOurModel();

        // if caption/type is not defined in $defaults then infer it from their field
        $analysingTheirModel = $ourModel->getReference($this->link)->createAnalysingTheirModel();
        $analysingTheirField = $analysingTheirModel->getField($theirFieldName);
        $defaults['type'] ??= $analysingTheirField->type;
        $defaults['enum'] ??= $analysingTheirField->enum;
        $defaults['values'] ??= $analysingTheirField->values;
        $defaults['caption'] ??= $analysingTheirField->caption;
        $defaults['ui'] = array_merge($defaults['ui'] ?? $analysingTheirField->ui, ['editable' => false]);

        $fieldExpression = $this->_addField($fieldName, false, $theirFieldName, $defaults);

        return $fieldExpression;
    }

    /**
     * Add multiple expressions by calling addField several times. Fields
     * may contain 3 types of elements:.
     *
     * ['name', 'surname'] - will import those fields as-is
     * ['full_name' => 'name', 'day_of_birth' => ['dob', 'type' => 'date']] - use alias and options
     * [['dob', 'type' => 'date']]  - use options
     *
     * @param array<string, array<mixed>>|array<int, string> $fields
     * @param array<string, mixed>                           $defaults
     *
     * @return $this
     */
    public function addFields(array $fields = [], array $defaults = [])
    {
        foreach ($fields as $ourFieldName => $ourFieldDefaults) {
            $ourFieldDefaults = array_merge($defaults, (array) $ourFieldDefaults);

            $theirFieldName = $ourFieldDefaults[0] ?? null;
            unset($ourFieldDefaults[0]);
            if (is_int($ourFieldName)) {
                $ourFieldName = $theirFieldName;
            }

            $this->addField($ourFieldName, $theirFieldName, $ourFieldDefaults);
        }

        return $this;
    }

    #[\Override]
    public function ref(Model $ourModelOrEntity, array $defaults = []): Model
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        $theirModel = parent::ref($ourModelOrEntity, $defaults);

        if ($ourModelOrEntity->isEntity() && $this->getOurFieldValue($ourModelOrEntity) !== null) {
            // materialized condition already added in parent/HasOne class
        } else {
            // handle deep traversal using an expression
            $ourFieldExpression = $ourModelOrEntity->action('field', [$this->getOurField()]);

            $theirModel->getModel(true)
                ->addCondition($this->getTheirFieldName($theirModel), $ourFieldExpression);
        }

        return $theirModel;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     *
     * @param array<string, mixed> $defaults
     */
    public function refLink(array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        $theirModel->addCondition($this->getTheirFieldName($theirModel), $this->referenceOurValue());

        return $theirModel;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', ['model' => [User::class]])
     *     ->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     *
     * @param array<string, mixed> $defaults
     */
    public function addTitle(array $defaults = []): SqlExpressionField
    {
        $ourModel = $this->getOurModel();

        $fieldName = $defaults['field'] ?? preg_replace('~_(' . preg_quote($ourModel->idField, '~') . '|id)$~', '', $this->link);

        $defaults['ui'] = array_merge(['visible' => true], $defaults['ui'] ?? [], ['editable' => false]);

        $fieldExpression = $this->_addField($fieldName, true, null, $defaults);

        // set ID field as not visible in grid by default
        if (!array_key_exists('visible', $this->getOurField()->ui)) {
            $this->getOurField()->ui['visible'] = false;
        }

        return $fieldExpression;
    }
}
