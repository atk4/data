<?php

declare(strict_types=1);

namespace atk4\data\Reference;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\FieldSqlExpression;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Reference\HasOneSql class.
 */
class HasOneSql extends HasOne
{
    /**
     * Creates expression which sub-selects a field inside related model.
     *
     * Returns Expression in case you want to do something else with it.
     *
     * @param string|Field|array $fieldName or [$field, ..defaults]
     */
    public function addField($fieldName, string $theirFieldName = null): FieldSqlExpression
    {
        if (is_array($fieldName)) {
            $defaults = $fieldName;
            if (!isset($defaults[0])) {
                throw (new Exception('Field name must be specified'))
                    ->addMoreInfo('field', $fieldName);
            }
            $fieldName = $defaults[0];
            unset($defaults[0]);
        } else {
            $defaults = [];
        }

        if ($theirFieldName === null) {
            $theirFieldName = $fieldName;
        }

        $ourModel = $this->getOurModel();

        // if caption is not defined in $defaults -> get it directly from the linked model field $theirFieldName
        $defaults['caption'] = $defaults['caption'] ?? $ourModel->refModel($this->link)->getField($theirFieldName)->getCaption();

        /** @var FieldSqlExpression $fieldExpression */
        $fieldExpression = $ourModel->addExpression($fieldName, array_merge(
            [
                function (Model $ourModel) use ($theirFieldName) {
                    // remove order if we just select one field from hasOne model
                    // that is mandatory for Oracle
                    return $ourModel->refLink($this->link)->action('field', [$theirFieldName])->reset('order');
                },
            ],
            $defaults
        ));

        $fieldExpression->read_only = false;
        $fieldExpression->never_save = true;

        // Will try to execute last
        $ourModel->onHook(Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($fieldName, $theirFieldName) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($fieldName) && !$ourModel->isDirty($this->our_field)) {
                $theirModel = $this->getTheirModel();

                $theirModel->addCondition($theirFieldName, $ourModel->get($fieldName));
                $ourModel->set($this->our_field, $theirModel->action('field', [$theirModel->id_field]));
                $ourModel->_unset($fieldName);
            }
        }, [], 21);

        return $fieldExpression;
    }

    /**
     * Add multiple expressions by calling addField several times. Fields
     * may contain 3 types of elements:.
     *
     * [ 'name', 'surname' ] - will import those fields as-is
     * [ 'full_name' => 'name', 'day_of_birth' => ['dob', 'type'=>'date'] ] - use alias and options
     * [ ['dob', 'type' => 'date'] ]  - use options
     *
     * You may also use second param to specify parameters:
     *
     * addFields(['from', 'to'], ['type' => 'date']);
     *
     * @param array $fields
     * @param array $groupDefaults
     *
     * @return $this
     */
    public function addFields($fields = [], $groupDefaults = [])
    {
        foreach ($fields as $fieldName => $defaults) {
            if (is_array($defaults)) {
                $field = array_merge($groupDefaults, $defaults);
                if (!isset($defaults[0])) {
                    throw (new Exception('Incorrect definition for addFields. Field name must be specified'))
                        ->addMoreInfo('fieldName', $fieldName)
                        ->addMoreInfo('alias', $defaults);
                }
                $defaults = $defaults[0];
            } else {
                $field = $groupDefaults;
            }

            if (is_numeric($fieldName)) {
                $fieldName = $defaults;
            }

            $field[0] = $fieldName;
            $this->addField($field, $defaults);
        }

        return $this;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     *
     * @param array $defaults Properties
     */
    public function refLink($defaults = []): Model
    {
        $theirModel = $this->getTheirModel($defaults);

        $theirModel->addCondition(
            $this->their_field ?: $theirModel->id_field,
            $this->referenceOurValue()
        );

        return $theirModel;
    }

    /**
     * Navigate to referenced model.
     *
     * @param array $defaults Properties
     */
    public function ref($defaults = []): Model
    {
        $theirModel = parent::ref($defaults);
        $ourModel = $this->getOurModel();

        if (!isset($ourModel->persistence) || !($ourModel->persistence instanceof Persistence\Sql)) {
            return $theirModel;
        }

        // If model is not loaded, then we are probably doing deep traversal
        if (!$ourModel->loaded()) {
            $values = $ourModel->action('field', [$this->our_field]);

            return $theirModel->addCondition($this->their_field ?: $theirModel->id_field, $values);
        }

        // At this point the reference
        // if our_field is the id_field and is being used in the reference
        // we should persist the relation in condtition
        // example - $model->load(1)->ref('refLink')->import($rows);
        if ($ourModel->loaded() && !$theirModel->loaded()) {
            if ($ourModel->id_field === $this->our_field) {
                $field = $this->their_field ?: $theirModel->id_field;
                $value = $ourModel->get($this->our_field ?: $ourModel->id_field);
                $theirModel->addCondition($field, $value);
            }
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
     *
     * @param array $defaults Properties
     */
    public function addTitle($defaults = []): FieldSqlExpression
    {
        if (!is_array($defaults)) {
            throw (new Exception('Argument to addTitle should be an array'))
                ->addMoreInfo('arg', $defaults);
        }

        $ourModel = $this->getOurModel();

        $field = $defaults['field'] ?? preg_replace('/_' . ($ourModel->id_field ?: 'id') . '$/i', '', $this->link);

        if ($ourModel->hasField($field)) {
            throw (new Exception('Field with this name already exists. Please set title field name manually addTitle([\'field\'=>\'field_name\'])'))
                ->addMoreInfo('field', $field);
        }

        /** @var FieldSqlExpression $fieldExpression */
        $fieldExpression = $ourModel->addExpression($field, array_replace_recursive(
            [
                function (Model $ourModel) {
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
        $ourModel->onHook(Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($field) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($field) && !$ourModel->isDirty($this->our_field)) {
                $theirModel = $this->getTheirModel();

                $theirModel->addCondition($theirModel->title_field, $ourModel->get($field));
                $ourModel->set($this->our_field, $theirModel->action('field', [$theirModel->id_field]));
            }
        }, [], 20);

        // Set ID field as not visible in grid by default
        if (!array_key_exists('visible', $ourModel->getField($this->our_field)->ui)) {
            $ourModel->getField($this->our_field)->ui['visible'] = false;
        }

        return $fieldExpression;
    }

    /**
     * Add a title of related entity as expression to our field.
     *
     * $order->hasOne('user_id', 'User')->addTitle();
     *
     * This will add expression 'user' equal to ref('user_id')['name'];
     *
     * @param array $defaults Properties
     *
     * @return $this
     */
    public function withTitle($defaults = [])
    {
        $this->addTitle($defaults);

        return $this;
    }
}
