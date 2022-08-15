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
     */
    private function _addField(string $fieldName, bool $theirFieldIsTitle, ?string $theirFieldName, array $defaults): SqlExpressionField
    {
        $ourModel = $this->getOurModel(null);

        $fieldExpression = $ourModel->addExpression($fieldName, array_merge([
            'expr' => function (Model $ourModel) use ($theirFieldIsTitle, $theirFieldName) {
                $theirModel = $ourModel->refLink($this->link);
                if ($theirFieldIsTitle) {
                    $theirFieldName = $theirModel->titleField;
                }

                // remove order if we just select one field from hasOne model, needed for Oracle
                return $theirModel->action('field', [$theirFieldName])->reset('order');
            },
        ], $defaults, [
            // allow to set our field value by an imported foreign field, but only when
            // the our field value is null
            'readOnly' => false,
        ]));

        $this->onHookToOurModel($ourModel, Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($fieldName, $theirFieldIsTitle, $theirFieldName) {
            if ($ourModel->isDirty($fieldName)) {
                $theirModel = $this->createTheirModel();
                if ($theirFieldIsTitle) {
                    $theirFieldName = $theirModel->titleField;
                }

                // when our field is not null or dirty too, update nothing, but check if the imported
                // field was changed to expected value implied by the relation
                if ($ourModel->isDirty($this->getOurFieldName()) || $ourModel->get($this->getOurFieldName()) !== null) {
                    $importedFieldValue = $ourModel->get($fieldName);
                    $expectedTheirEntity = $theirModel->loadBy($this->getTheirFieldName($theirModel), $ourModel->get($this->getOurFieldName()));
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
                    $newTheirEntity = $theirModel->loadBy($theirFieldName, $ourModel->get($fieldName));
                    $ourModel->set($this->getOurFieldName(), $newTheirEntity->get($this->getTheirFieldName($theirModel)));
                    $ourModel->_unset($fieldName);
                }
            }
        }, [], 20);

        return $fieldExpression;
    }

    /**
     * Creates expression which sub-selects a field inside related model.
     *
     * @return $this
     */
    public function addField(string $fieldName, string $theirFieldName = null, array $defaults = [])
    {
        if ($theirFieldName === null) {
            $theirFieldName = $fieldName;
        }

        $ourModel = $this->getOurModel(null);

        // if caption/type is not defined in $defaults -> get it directly from the linked model field $theirFieldName
        $refModelField = $ourModel->refModel($this->link)->getField($theirFieldName);
        $defaults['type'] ??= $refModelField->type;
        $defaults['enum'] ??= $refModelField->enum;
        $defaults['values'] ??= $refModelField->values;
        $defaults['caption'] ??= $refModelField->caption;
        $defaults['ui'] = array_merge($defaults['ui'] ?? $refModelField->ui, ['editable' => false]);

        $this->_addField($fieldName, false, $theirFieldName, $defaults);

        return $this;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     */
    public function refLink(Model $ourModel, array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        $theirModel->addCondition($this->getTheirFieldName($theirModel), $this->referenceOurValue());

        return $theirModel;
    }

    /**
     * Navigate to referenced model.
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        $theirModel = parent::ref($ourModel, $defaults);
        $ourModel = $this->getOurModel($ourModel);

        if ($ourModel->isEntity() && $this->getOurFieldValue($ourModel) !== null) {
            // materialized condition already added in parent/HasOne class
        } else {
            // handle deep traversal using an expression
            $ourFieldExpression = $ourModel->action('field', [$this->getOurField()]);

            $theirModel->getModel(true)
                ->addCondition($this->getTheirFieldName($theirModel), $ourFieldExpression);
        }

        return $theirModel;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', 'User')->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     */
    public function addTitle(array $defaults = []): SqlExpressionField
    {
        $ourModel = $this->getOurModel(null);

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
