<?php

namespace atk4\data;

class Persistence_Array extends Persistence {

    public $data;

    function __construct($data)
    {
        $this->data = $data;
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

        $m = parent::add($m, $defaults);
        $m->persistence = $this;
        $m->persistence_data = [];
    }

    public function load(Model $m, $id)
    {
        if (!isset($this->data[$m->table])) {
            throw Exception ([
                'Table was not found in the array data source',
                'table'=>$m->table
            ]);
        }

        if (!isset($this->data[$m->table][$id])) {
            throw Exception([
                'Record with specified ID was not found',
                'id'=>$id
            ], 404);
        }

        return $this->tryLoad($m, $id);
    }

    public function tryLoad(Model $m, $id)
    {
        if (!isset($this->data[$m->table][$id])) {
            return false;
        }

        $m->data = $this->data[$m->table][$id];
        $m->id = $id;
    }

    public function insert(Model $m, $data)
    {
        $id = $this->generateNewID($m);
        $this->data[$m->table][$id] = $data;
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

    public function generateNewID($m)
    {
        $ids = array_keys($this->data[$m->table]);

        $type = $model->getElement($model->id_field)->type;

        if ($type === 'integer') {
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
