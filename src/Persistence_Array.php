<?php // vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Implements persistance driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Persistence_Array extends Persistence {

    public $data;

    function __construct(&$data)
    {
        $this->data =& $data;
    }

    /**
     * Associate model with the data driver
     */
    public function add($m, $defaults = [])
    {
        if (isset($defaults[0])) {
            $m->table = $defaults[0];
            unset($defaults[0]);
        }

        $defaults = array_merge([
            '_default_class_join' => 'atk4\data\Join_Array',
        ], $defaults);

        return parent::add($m, $defaults);
    }

    public function load(Model $m, $id, $table = null)
    {
        if (!isset($this->data[$m->table]) && !isset($table)) {
            throw Exception ([
                'Table was not found in the array data source',
                'table'=>$m->table
            ]);
        }
        if (!isset($this->data[$table ?: $m->table][$id])) {
            throw new Exception([
                'Record with specified ID was not found',
                'id'=>$id
            ], 404);
        }

        return $this->tryLoad($m, $id, $table);
    }

    public function tryLoad(Model $m, $id, $table = null)
    {

        if (!isset($table)) {
            $table = $m->table;
        }

        if (!isset($this->data[$table][$id])) {
            return false;
        }

        return $this->data[$table][$id];
    }

    public function insert(Model $m, $data, $table = null)
    {
        if (!$table) {
            $table = $m->table;
        }
        $id = $this->generateNewID($m, $table);
        $data[$m->id_field] = $id;
        $this->data[$table][$id] = $data;
        return $id;
    }

    public function update(Model $m, $id, $data)
    {
        $this->data[$m->table][$id] =
            array_merge(
                $this->data[$m->table][$id],
                $data
            );
        return $id;
    }

    public function generateNewID($m, $table = null)
    {
        if (!$table) {
            $table = $m->table;
        }

        $ids = array_keys($this->data[$table]);

        $type = $m->getElement($m->id_field)->type;

        if ($type === 'int') {
            return count($ids) === 0 ? 1 : (max($ids) + 1);
        } elseif ($type == 'string') {
            return uniqid();
        } else {
            throw new Exception([
                'Unknown id type. Array supports type=integer or type=string only',
                'type'=>$type
            ]);
        }
    }
}
