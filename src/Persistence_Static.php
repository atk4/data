<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Implements a very basic array-access pattern:
 *
 * $m = new Model(Persistence_Static(['hello', 'world']));
 * $m->load(1);
 *
 * echo $m['name'];  // world
 */
class Persistence_Static extends Persistence_Array
{
    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array &$data
     */
    public function __construct($data = null)
    {
        $data2 = [];
        if ($data) foreach($data as $id=>$name) {
            $data2[$id] = ['id'=>$id, 'name'=>$name];
        }

        $this->addHook('afterAdd', function($p, $m) {
            if (!$m->hasElement($m->title_field)) {
                $m->addField($m->title_field);
            }
        });

        // convert array
        parent::__construct($data2);
    }
}
