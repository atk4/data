<?php // vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

class Field_SQL_One extends Field_One
{

    function addField($field, $their_field)
    {
        $this->owner->addExpression($field, function($m) use ($their_field) {
            return $m->refLink($this->link)->action('field', [$their_field]);
        });
    }

    /**
     * Creates model that can be used for generating sub-query acitons
     */
    public function refLink()
    {
        $m = $this->getModel();
        $m ->addCondition(
                $this->their_field ?: ($m->id_field),
                $this->referenceOurValue($m)
            );
        return $m;
    }

    function addTitleField()
    {

    }
}
