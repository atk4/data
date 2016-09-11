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
     * @param string|Field $field
     * @param string|null  $their_field
     * @param array        $defaults    Properties
     *
     * @return Field_SQL_Expression
     */
    public function addField($field, $their_field = null, $defaults = [])
    {
        if ($their_field === null) {
            $their_field = $field;
        }

        return $this->owner->addExpression($field, array_merge([
            function ($m) use ($their_field) {
                return $m->refLink($this->link)->action('field', [$their_field]);
            }, ],
            $defaults
        ));
    }

    /**
     * Add multiple expressions by calling addField several times.
     *
     * @param array $fields
     *
     * @return $this
     */
    public function addFields($fields = [])
    {
        foreach ($fields as $field => $alias) {
            if (is_numeric($field)) {
                $this->addField($alias);
            } else {
                $this->addField($field, $alias);
            }
        }

        return $this;
    }

    /**
     * Creates model that can be used for generating sub-query actions.
     *
     * @return Model
     */
    public function refLink()
    {
        $m = $this->getModel();
        $m->addCondition(
            $this->their_field ?: ($m->id_field),
            $this->referenceOurValue($m)
        );

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
        $field = str_replace('_id', '', $this->link);
        $ex = $this->owner->addExpression($field, array_merge_recursive(
            [
                function ($m) {
                    $mm = $m->refLink($this->link);

                    return $mm->action('field', [$mm->title_field]);
                },
                'type' => 'string',
                'ui'   => ['editable' => false, 'visible' => true],
            ],
            $defaults,
            [
                // to be able to change title field, but not save and
                // afterSave hook will take care of the rest
                'read_only'  => false,
                'never_save' => true,
            ]
        ));

        $this->owner->addHook('beforeSave', function ($m) use ($field) {
            if ($m->isDirty($field) && !$m->isDirty($this->link)) {
                $mm = $m->getRef($this->link)->getModel();

                $mm->addCondition($mm->title_field, $m[$field]);
                $m[$this->link] = $mm->action('field', [$mm->id_field]);
            }
        });

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
