<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;

class HasOneSql extends HasOne
{
    /**
     * Creates expression which sub-selects a field inside related model.
     */
    public function addField(string $fieldName, string $theirFieldName = null, array $defaults = []): SqlExpressionField
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
        $defaults['ui'] ??= $refModelField->ui;

        $fieldExpression = $ourModel->addExpression($fieldName, array_merge(
            [
                'expr' => function (Model $ourModel) use ($theirFieldName) {
                    // remove order if we just select one field from hasOne model
                    // that is mandatory for Oracle
                    return $ourModel->refLink($this->link)->action('field', [$theirFieldName])->reset('order');
                },
            ],
            $defaults,
            [
                // to be able to change field, but not save it
                // afterSave hook will take care of the rest
                'read_only' => false,
                'never_save' => true,
            ]
        ));

        // Will try to execute last
        $this->onHookToOurModel($ourModel, Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($fieldName, $theirFieldName) {
            // if field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($fieldName) && !$ourModel->isDirty($this->our_field)) {
                $theirModel = $this->createTheirModel();

                $theirModel->addCondition($theirFieldName, $ourModel->get($fieldName));
                $ourModel->set($this->getOurFieldName(), $theirModel->action('field', [$theirModel->id_field]));
                $ourModel->_unset($fieldName);
            }
        }, [], 20);

        return $fieldExpression;
    }

    /**
     * Add multiple expressions by calling addField several times. Fields
     * may contain 3 types of elements:.
     *
     * [ 'name', 'surname' ] - will import those fields as-is
     * [ 'full_name' => 'name', 'day_of_birth' => ['dob', 'type' => 'date'] ] - use alias and options
     * [ ['dob', 'type' => 'date'] ]  - use options
     *
     * You may also use second param to specify parameters:
     *
     * addFields(['from', 'to'], ['type' => 'date']);
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

    /**
     * Creates model that can be used for generating sub-query actions.
     */
    public function refLink(Model $ourModel, array $defaults = []): Model
    {
        $theirModel = $this->createTheirModel($defaults);

        $theirModel->addCondition(
            $this->their_field ?: $theirModel->id_field,
            $this->referenceOurValue()
        );

        return $theirModel;
    }

    /**
     * Navigate to referenced model.
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        $theirModel = parent::ref($ourModel, $defaults);
        $ourModel = $this->getOurModel($ourModel);

        $theirFieldName = $this->their_field ?? $theirModel->id_field; // TODO why not $this->getTheirFieldName() ?

        if ($ourModel->isEntity()) {
            $theirModel->getModel()
                ->addCondition($theirFieldName, $this->getOurFieldValue($ourModel));
        } else {
            // handles the deep traversal using an expression
            $ourFieldExpression = $ourModel->action('field', [$this->getOurField()]);

            $theirModel->getModel(true)
                ->addCondition($theirFieldName, $ourFieldExpression);
        }

        return $theirModel;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', 'User')->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     *
     * This method returns newly created expression field.
     */
    public function addTitle(array $defaults = []): SqlExpressionField
    {
        $ourModel = $this->getOurModel(null);

        $fieldName = $defaults['field'] ?? preg_replace('~_(' . preg_quote($ourModel->id_field, '~') . '|id)$~', '', $this->link);

        $fieldExpression = $ourModel->addExpression($fieldName, array_replace_recursive(
            [
                'expr' => function (Model $ourModel) {
                    $theirModel = $ourModel->refLink($this->link);

                    return $theirModel->action('field', [$theirModel->title_field])->reset('order');
                },
                'type' => null,
                'ui' => ['editable' => false, 'visible' => true],
            ],
            $defaults,
            [
                // to be able to change title field, but not save it
                // afterSave hook will take care of the rest
                'read_only' => false,
                'never_save' => true,
            ]
        ));

        // Will try to execute last
        $this->onHookToOurModel($ourModel, Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($fieldName) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($fieldName) && !$ourModel->isDirty($this->our_field)) {
                $theirModel = $this->createTheirModel();

                $theirModel->addCondition($theirModel->title_field, $ourModel->get($fieldName));
                $ourModel->set($this->getOurFieldName(), $theirModel->action('field', [$theirModel->id_field]));
            }
        }, [], 20);

        // Set ID field as not visible in grid by default
        if (!array_key_exists('visible', $this->getOurField()->ui)) {
            $this->getOurField()->ui['visible'] = false;
        }

        return $fieldExpression;
    }
}
