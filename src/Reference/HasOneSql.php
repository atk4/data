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
     * @param string|Field|array $ourFieldName or [$field, ..defaults]
     * @param string|\Closure    $theirFieldName
     */
    public function addField($ourFieldName, $theirFieldName = null): FieldSqlExpression
    {
        if (is_array($ourFieldName)) {
            $defaults = $ourFieldName;
            if (!isset($defaults[0])) {
                throw (new Exception('Field name must be specified'))
                    ->addMoreInfo('field', $ourFieldName);
            }
            $ourFieldName = $defaults[0];
            unset($defaults[0]);
        } else {
            $defaults = [];
        }

        if ($theirFieldName === null) {
            $theirFieldName = $ourFieldName;
        }

        $ourModel = $this->getOurModel();

        // if caption is not defined in $defaults -> get it directly from the linked model field $theirFieldName
        $defaults['caption'] = $defaults['caption'] ?? function (Field $field) use ($theirFieldName) {
            return $this->getOurModel()->refModel($this->link)->getField($theirFieldName)->getCaption();
        };

        /** @var FieldSqlExpression $fieldExpression */
        $fieldExpression = $ourModel->addExpression($ourFieldName, array_merge(
            [
                function (Model $ourModel) use ($theirFieldName) {
                    if ($theirFieldName instanceof \Closure) {
                        $theirFieldName = $theirFieldName($this);
                    }

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
        $ourModel->onHook(Model::HOOK_BEFORE_SAVE, function (Model $ourModel) use ($ourFieldName, $theirFieldName) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($ourModel->isDirty($ourFieldName) && !$ourModel->isDirty($this->our_field)) {
                if ($theirFieldName instanceof \Closure) {
                    $theirFieldName = $theirFieldName($this);
                }

                $theirModel = $this->getTheirModel();

                $theirModel->addCondition($theirFieldName, $ourModel->get($ourFieldName));
                $ourModel->set($this->getOurFieldName(), $theirModel->action('field', [$theirModel->id_field]));
                $ourModel->_unset($ourFieldName);
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
     * @param array $defaults
     *
     * @return $this
     */
    public function addFields($fields = [], $defaults = [])
    {
        foreach ($fields as $ourFieldName => $ourFieldDefaults) {
            $ourFieldDefaults = array_merge($defaults, (array) $ourFieldDefaults);

            if (!isset($ourFieldDefaults[0])) {
                throw (new Exception('Incorrect definition for addFields. Field name must be specified'))
                    ->addMoreInfo('ourFieldName', $ourFieldName)
                    ->addMoreInfo('ourFieldDefaults', $ourFieldDefaults);
            }

            $theirFieldName = $ourFieldDefaults[0];

            if (is_numeric($ourFieldName)) {
                $ourFieldName = $theirFieldName;
            }

            $ourFieldDefaults[0] = $ourFieldName;

            $this->addField($ourFieldDefaults, $theirFieldName);
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

        $theirField = $this->their_field ?: $theirModel->id_field;
        $ourField = $this->getOurField();

        // At this point the reference
        // if our_field is the id_field and is being used in the reference
        // we should persist the relation in condtition
        // example - $model->load(1)->ref('refLink')->import($rows);
        if ($ourModel->loaded() && !$theirModel->loaded()) {
            if ($ourModel->id_field === $this->getOurFieldName()) {
                return $theirModel->addCondition($theirField, $this->getOurFieldValue());
            }
        }

        // handles the deep traversal using an expression
        $ourFieldExpression = $ourModel->action('field', [$ourField]);

        return $theirModel->addCondition($theirField, $ourFieldExpression);
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

        // Set ID field as not visible in grid by default
        if (!array_key_exists('visible', $this->getOurField()->ui)) {
            $this->getOurField()->ui['visible'] = false;
        }

        $ourModel = $this->getOurModel();

        $fieldName = $defaults['field'] ?? preg_replace('/_' . $ourModel->id_field . '$/i', '', $this->link);

        return $this->addField($fieldName, function (self $reference) {
            return $reference->getOurModel()->refModel($this->link)->title_field;
        });
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
