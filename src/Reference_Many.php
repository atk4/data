<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Reference_Many extends Reference
{
    /**
     * Returns our field value or id.
     *
     * @return mixed
     */
    protected function getOurValue()
    {
        if ($this->owner->loaded()) {
            return $this->our_field
                ? $this->owner[$this->our_field]
                : $this->owner->id;
        } else {
            // create expression based on existing conditions
            return $this->owner->action(
                'field',
                [
                    $this->our_field ?: ($this->owner->id_field ?: 'id'),
                ]
            );
        }
    }

    /**
     * Returns our field or id field.
     *
     * @return Field
     */
    protected function referenceOurValue()
    {
        $this->owner->persistence_data['use_table_prefixes'] = true;

        return $this->owner->getElement($this->our_field ?: ($this->owner->id_field ?: 'id'));
    }

    /**
     * Returns referenced model with condition set.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = [])
    {
        return $this->getModel($defaults)
            ->addCondition(
                $this->their_field ?: ($this->owner->table.'_'.($this->owner->id_field ?: 'id')),
                $this->getOurValue()
            );
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
        return $this->getModel($defaults)
            ->addCondition(
                $this->their_field ?: ($this->owner->table.'_'.($this->owner->id_field ?: 'id')),
                $this->referenceOurValue()
            );
    }

    /**
     * Adds field as expression to owner model.
     * Used in aggregate strategy.
     *
     * @param string $n        Field name
     * @param array  $defaults Properties
     *
     * @return Field_Callback
     */
    public function addField($n, $defaults = [])
    {
        if (!isset($defaults['aggregate'])) {
            throw new Exception([
                '"aggregate" strategy should be defined for oneToMany field',
                'field'    => $n,
                'defaults' => $defaults,
            ]);
        }

        $field = isset($defaults['field']) ? $defaults['field'] : $n;

        $e = $this->owner->addExpression($n, array_merge([
            function () use ($defaults, $field) {
                return $this->refLink()->action('fx0', [$defaults['aggregate'], $field]);
            }, ],
            $defaults
        ));

        if (isset($defaults['type'])) {
            $e->type = $defaults['type'];
        } else {
            $e->type = $this->guessFieldType($field);
        }

        return $e;
    }

    /**
     * Adds multiple fields.
     *
     * @see addField()
     *
     * @param array $fields Array of fields
     *
     * @return $this
     */
    public function addFields($fields = [])
    {
        foreach ($fields as $field) {
            $name = $field[0];
            unset($field[0]);
            $this->addField($name, $field);
        }

        return $this;
    }
}
