<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Reference_SQL_One extends Reference_One
{
    /**
     * Creates expression which sub-selects a field inside related model.
     *
     * Returns Expression in case you want to do something else with it.
     *
     * @param string|Field|array $field       or [$field, ..defaults]
     * @param string|null        $their_field
     *
     * @return Field_SQL_Expression
     */
    public function addField($field, $their_field = null)
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

        $e = $this->owner->addExpression($field, array_merge([
            function ($m) use ($their_field) {
                // remove order if we just select one field from hasOne model
                // that is mandatory for Oracle
                return $m->refLink($this->link)->action('field', [$their_field])->reset('order');
            }, ],
            $defaults
        ));

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
     * @return Model
     */
    public function refLink($defaults = [])
    {
        $m = $this->getModel($defaults);

        $m->addCondition(
            $this->their_field ?: ($m->id_field),
            $this->referenceOurValue($m)
        );

        return $m;
    }

    /**
     * Navigate to referenced model.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = [])
    {
        $m = parent::ref($defaults);

        // If model is not loaded, then we are probably doing deep traversal
        if (!$this->owner->loaded() && isset($this->owner->persistence) && $this->owner->persistence instanceof Persistence_SQL) {
            $values = $this->owner->action('field', [$this->our_field]);

            return $m->addCondition($this->their_field ?: $m->id_field, $values);
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
     * @return Field_SQL_Expression
     */
    public function addTitle($defaults = [])
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

        if ($this->owner->hasElement($field)) {
            throw new Exception([
                'Field with this name already exists. Please set title field name manually addTitle([\'field\'=>\'field_name\'])',
                'field' => $field,
            ]);
        }

        $ex = $this->owner->addExpression($field, array_merge_recursive(
            [
                function ($m) {
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
        $this->owner->addHook('beforeSave', function ($m) use ($field) {
            if ($m->isDirty($field) && !$m->isDirty($this->link)) {
                $mm = $m->getRef($this->link)->getModel();

                $mm->addCondition($mm->title_field, $m[$field]);
                $m[$this->link] = $mm->action('field', [$mm->id_field]);
            }
        }, null, 20);

        // Set ID field as not visible in grid by default
        if (!isset($this->owner->getElement($this->our_field)->ui['visible'])) {
            $this->owner->getElement($this->our_field)->ui['visible'] = false;
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
     * @return $this
     */
    public function withTitle($defaults = [])
    {
        $this->addTitle($defaults);

        return $this;
    }
}
