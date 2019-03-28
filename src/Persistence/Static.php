<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Implements a very basic array-access pattern:.
 *
 * $m = new Model(Persistence_Static(['hello', 'world']));
 * $m->load(1);
 *
 * echo $m['name'];  // world
 */
class Persistence_Static extends Persistence_Array
{
    /**
     * This will be the title field for the model.
     *
     * @var string
     */
    public $titleForModel = null;

    /**
     * Populate the following fields for the model.
     *
     * @var array
     */
    public $fieldsForModel = [];

    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array $data Static data in one of supported formats
     */
    public function __construct($data = null)
    {
        if (!is_array($data)) {
            throw new Exception(['Static data should be array of strings or array of hashes', 'data'=>$data]);
        }

        // chomp off first row, we will use it to deduct fields
        $row1 = reset($data);

        $this->addHook('afterAdd', [$this, 'afterAdd']);

        if (!is_array($row1)) {
            // We are dealing with array of strings. Convert it into array of hashes
            array_walk($data, function (&$str, $key) {
                $str = ['id'=>$key, 'name'=>$str];
            });

            $this->titleForModel = 'name';
            $this->fieldsForModel = ['name'=>[]];

            return parent::__construct($data);
        }

        if (isset($row1['name'])) {
            $this->titleForModel = 'name';
        } elseif (isset($row1['title'])) {
            $this->titleForModel = 'title';
        }

        $key_override = [];
        $def_types = [];
        $must_override = false;

        foreach ($row1 as $key=>$value) {

            // id information present, use it instead
            if ($key == 'id') {
                $must_override = true;
            }

            // try to detect type of field by its value
            if (is_int($value)) {
                $def_types[] = ['type'=>'integer'];
            } elseif ($value instanceof \DateTime) {
                $def_types[] = ['type'=>'datetime'];
            } elseif (is_bool($value)) {
                $def_types[] = ['type'=>'boolean'];
            } elseif (is_float($value)) {
                $def_types[] = ['type'=>'float'];
            } elseif (is_array($value)) {
                $def_types[] = ['type'=>'array'];
            } elseif (is_object($value)) {
                $def_types[] = ['type'=>'object'];
            } else {
                $def_types[] = [];
            }

            // if title is not set, use first key
            if (!$this->titleForModel) {
                if (is_int($key)) {
                    $key_override[] = 'name';
                    $this->titleForModel = 'name';
                    $must_override = true;
                    continue;
                } else {
                    $this->titleForModel = $key;
                }
            }

            if (is_int($key)) {
                $key_override[] = 'field'.$key;
                $must_override = true;
                continue;
            }

            $key_override[] = $key;
        }

        if ($must_override) {
            $data2 = [];

            foreach ($data as $key=>$row) {
                $row = array_combine($key_override, $row);
                if (isset($row['id'])) {
                    $key = $row['id'];
                }
                $data2[$key] = $row;
            }
            $data = $data2;
        }

        $this->fieldsForModel = array_combine($key_override, $def_types);
        parent::__construct($data);
    }

    /**
     * Automatically adds missing model fields.
     * Called from AfterAdd hook.
     *
     * @param Persistence_Static $p
     * @param Model              $m
     */
    public function afterAdd($p, $m)
    {
        if ($p->titleForModel) {
            $m->title_field = $p->titleForModel;
        }

        foreach ($this->fieldsForModel as $field=>$def) {
            if ($m->hasElement($field)) {
                continue;
            }

            // add new field
            $m->addField($field, $def);
        }
    }
}
