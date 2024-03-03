<?php

declare(strict_types=1);

namespace Atk4\Data;

class Model2Inner extends Model
{
    /** @var \WeakReference<Model2> */
    protected $outerModelWeakref;

    protected function getOuterModel(): Model2
    {
        return $this->outerModelWeakref->get();
    }

    protected function init(): void
    {
        parent::init();

        if ($this->idField) {
            $this->removeField($this->idField);
        }

        $om = $this->getOuterModel();

        foreach ($om->getFields() as $name => $oField) {
            // calculated field is never a table/source field
            if ($oField instanceof Field\SqlExpressionField) {
                continue;
            }

            // multiple fields can exists mapped to one/same actual fields
            // example: https://github.com/atk4/data/blob/3.1.0/tests/JoinSqlTest.php#L400
            $actualName = $oField->actual ?? $name;
            if ($this->hasField($actualName)) {
                continue;
            }

            // skip non-only fields if configured
            // example: https://github.com/atk4/data/blob/3.1.0/tests/LimitOrderTest.php#L29
            // must be improved, breaks https://github.com/atk4/data/blob/3.1.0/tests/LimitOrderTest.php#L161
            if ($om->onlyFields && !in_array($name, $om->onlyFields, true) && !$oField->system) {
                continue;
            }

            // skip fields from another/joined table
            // https://github.com/atk4/data/blob/3.1.0/tests/ConditionSqlTest.php#L251
            if ($oField->hasJoin()) {
                continue;
            }

            $fieldDefaults = [get_class($oField)];
            foreach ([
                'type',
                'system',
                'default',
                'neverPersist',
                'neverSave',
                'readOnly',
                'nullable',
                'required',
            ] as $prop) {
                $fieldDefaults[$prop] = $oField->{$prop};
            }

            $this->addField($actualName, $fieldDefaults);
        }
    }
}
