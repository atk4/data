<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Reference;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Reference;

/**
 * ContainsMany reference.
 */
class ContainsMany extends ContainsOne
{
    /**
     * Returns referenced model.
     *
     * @param array $defaults Properties
     *
     * @return Model
     */
    public function ref($defaults = []) : Model
    {
        // model should be loaded
        if (!$this->owner->loaded()) {
            throw new Exception(['Model should be loaded!', 'model' => get_class($this->owner)]);
        }

        // get model
        // will not use ID field
        $m = $this->getModel(array_merge($defaults, [
            'contained_in_model'      => $this->owner,
            'contained_in_root_model' => $this->owner->contained_in_root_model ?: $this->owner,
            /*'id_field' => false,*/
            'table' => $this->table_alias,
        ]));

        // set data source of referenced array persistence
        $rows = $this->owner[$this->our_field] ?: [];
        foreach ($rows as $id=>$row) {
            $rows[$id] = $this->owner->persistence->typecastLoadRow($m, $row);
        }
        $m->persistence->data = [$this->table_alias => ($rows ?: [])];

        // set some hooks for ref_model
        $m->addHook(['afterSave', 'afterDelete'], function ($m) {
            // NOTE - it would be super to use array_values() here around export() because then json_encode
            // will encode this as actual JS array not object, but sadly then model id functionality breaks :(
            $rows = $m->export(/*null,null,false*/);
//var_dump([get_class($m)=>$rows]);

//var_dump(['rows_before'=>$rows]);
            // use root model persistence for typecasting
            $owner = $this->owner->contained_in_root_model ?: $this->owner;
            foreach ($rows as $id=>$row) {
                $rows[$id] = $owner->persistence->typecastSaveRow($m, $row);
            }
//var_dump(['rows_after'=>$rows]);

            $this->owner->save([$this->our_field => $rows ?: null]);
            //$m->breakHook(false);
        });

        return $m;
    }
}
