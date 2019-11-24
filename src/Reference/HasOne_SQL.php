<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Reference;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Field_SQL_Expression;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Reference\HasOne_SQL class.
 */
class HasOne_SQL extends HasOne
{
    /**
     * Creates expression which sub-selects a field inside related model.
     *
     * Returns Expression in case you want to do something else with it.
     *
     * @param string|Field|array $field       or [$field, ..defaults]
     * @param string|null        $their_field
     *
     * @throws Exception
     * @throws \atk4\core\Exception
     *
     * @return Field_SQL_Expression
     */
    public function addField($field, ?string $their_field = null) : Field_SQL_Expression
    {
        if (is_array($field)) {
            $defaults = $field;
            if (!isset($defaults[0])) {
                throw new Exception([
                    'Field name must be specified',
                    'field' => $field,
                ]);
            }
            $field = $defaults[0];
            unset($defaults[0]);
        } else {
            $defaults = [];
        }

        if ($their_field === null) {
            $their_field = $field;
        }

        // if caption is not defined in $defaults -> get it directly from the linked model field $their_field
        $defaults['caption'] = $defaults['caption'] ?? $this->owner->refModel($this->link)->getField($their_field)->getCaption();

        /** @var Field_SQL_Expression $e */
        $e = $this->owner->addExpression($field, array_merge([
            function (Model $m) use ($their_field) {
                // remove order if we just select one field from hasOne model
                // that is mandatory for Oracle
                return $m->refLink($this->link)->action('field', [$their_field])->reset('order');
            }, ],
            $defaults
        ));

        $e->read_only = false;
        $e->never_save = true;

        // Will try to execute last
        $this->owner->addHook('beforeSave', function (Model $m) use ($field, $their_field) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($m->isDirty($field) && !$m->isDirty($this->our_field)) {
                $mm = $this->getModel();

                $mm->addCondition($their_field, $m[$field]);
                $m[$this->our_field] = $mm->action('field', [$mm->id_field]);
                unset($m[$field]);
            }
        }, null, 21);

        return $e;
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
     * @throws Exception
     * @throws \atk4\core\Exception
     *
     * @return $this
     */
    public function addFields($fields = [], $defaults = [])
    {
        foreach ($fields as $field => $alias) {
            if (is_array($alias)) {
                $d = array_merge($defaults, $alias);
                if (!isset($alias[0])) {
                    throw new Exception([
                        'Incorrect definition for addFields. Field name must be specified',
                        'field' => $field,
                        'alias' => $alias,
                    ]);
                }
                $alias = $alias[0];
            } else {
                $d = $defaults;
            }

            if (is_numeric($field)) {
                $field = $alias;
            }

            $d[0] = $field;
            $this->addField($d, $alias);
        }

        return $this;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     *
     * @param array $defaults Properties
     *
     * @throws \atk4\core\Exception
     *
     * @return Model
     */
    public function refLink($defaults = []) : Model
    {
        $m = $this->getModel($defaults);

        $m->addCondition(
            $this->their_field ?: ($m->id_field),
            $this->referenceOurValue()
        );

        return $m;
    }

    /**
     * Navigate to referenced model.
     *
     * @param array $defaults Properties
     *
     * @throws Exception
     * @throws \atk4\core\Exception
     *
     * @return Model
     */
    public function ref($defaults = []) : Model
    {
        $m = parent::ref($defaults);

        if (!isset($this->owner->persistence) || !($this->owner->persistence instanceof Persistence\SQL)) {
            return $m;
        }

        // If model is not loaded, then we are probably doing deep traversal
        if (!$this->owner->loaded()) {
            $values = $this->owner->action('field', [$this->our_field]);

            return $m->addCondition($this->their_field ?: $m->id_field, $values);
        }

        // At this point the reference
        // if our_field is the id_field and is being used in the reference
        // we should persist the relation in condtition
        // example - $m->load(1)->ref('refLink')->import($rows);
        if ($this->owner->loaded() && !$m->loaded()) {
            if ($this->owner->id_field == $this->our_field) {
                $condition_field = $this->their_field ?: $m->id_field;
                $condition_value = $this->owner[$this->our_field ?: $this->owner->id_field];
                $m->addCondition($condition_field, $condition_value);
            }
        }

        return $m;
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
     *
     * @throws Exception
     * @throws \atk4\core\Exception
     *
     * @return Field_SQL_Expression
     */
    public function addTitle($defaults = []) : Field_SQL_Expression
    {
        if (!is_array($defaults)) {
            throw new Exception([
                'Argument to addTitle should be an array',
                'arg' => $defaults,
            ]);
        }

        $field = isset($defaults['field'])
                    ? $defaults['field']
                    : preg_replace('/_'.($this->owner->id_field ?: 'id').'$/i', '', $this->link);

        if ($this->owner->hasField($field)) {
            throw new Exception([
                'Field with this name already exists. Please set title field name manually addTitle([\'field\'=>\'field_name\'])',
                'field' => $field,
            ]);
        }

        /** @var Field_SQL_Expression $ex */
        $ex = $this->owner->addExpression($field, array_merge_recursive(
            [
                function (Model $m) {
                    $mm = $m->refLink($this->link);

                    return $mm->action('field', [$mm->title_field])->reset('order');
                },
                'type' => null,
                'ui'   => ['editable' => false, 'visible' => true],
            ],
            $defaults,
            [
                // to be able to change title field, but not save it
                // afterSave hook will take care of the rest
                'read_only'  => false,
                'never_save' => true,
            ]
        ));

        // Will try to execute last
        $this->owner->addHook('beforeSave', function (Model $m) use ($field) {
            // if title field is changed, but reference ID field (our_field)
            // is not changed, then update reference ID field value
            if ($m->isDirty($field) && !$m->isDirty($this->our_field)) {
                $mm = $this->getModel();

                $mm->addCondition($mm->title_field, $m[$field]);
                $m[$this->our_field] = $mm->action('field', [$mm->id_field]);
            }
        }, null, 20);

        // Set ID field as not visible in grid by default
        if (!array_key_exists('visible', $this->owner->getField($this->our_field)->ui)) {
            $this->owner->getField($this->our_field)->ui['visible'] = false;
        }

        return $ex;
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
     * @throws Exception
     * @throws \atk4\core\Exception
     *
     * @return $this
     */
    public function withTitle($defaults = [])
    {
        $this->addTitle($defaults);

        return $this;
    }
}
