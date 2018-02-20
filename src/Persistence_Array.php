<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Implements persistence driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Persistence_Array extends Persistence
{
    /**
     * Array of data.
     *
     * @var array
     */
    public $data;

    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array &$data
     */
    public function __construct(&$data)
    {
        $this->data = &$data;
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $m        Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @return Model
     */
    public function add($m, $defaults = [])
    {
        if (isset($defaults[0])) {
            $m->table = $defaults[0];
            unset($defaults[0]);
        }

        $defaults = array_merge([
            '_default_seed_join' => 'atk4\data\Join_Array',
        ], $defaults);

        $m = parent::add($m, $defaults);

        if ($f = $m->hasElement($m->id_field)) {
            if (!$f->type) {
                $f->type = 'integer';
            }
        }

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there
        if (!$m->table) {
            $m->table = 'data'; // fake table name 'data'
            if (!isset($this->data[$m->table]) || count($this->data) != 1) {
                $this->data = [$m->table => $this->data];
            }
        }

        // if there is no such table in persistence, then create empty one
        if (!isset($this->data[$m->table])) {
            $this->data[$m->table] = [];
        }

        return $m;
    }

    /**
     * Loads model and returns data record.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     *
     * @return array|false
     */
    public function load(Model $m, $id, $table = null)
    {
        if (isset($m->table) && !isset($this->data[$m->table])) {
            throw new Exception([
                'Table was not found in the array data source',
                'table' => $m->table,
            ]);
        }

        if (!isset($this->data[$table ?: $m->table][$id])) {
            throw new Exception([
                'Record with specified ID was not found',
                'id' => $id,
            ], 404);
        }

        return $this->tryLoad($m, $id, $table);
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     *
     * @return array|false
     */
    public function tryLoad(Model $m, $id, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        if (!isset($this->data[$table][$id])) {
            return false; // no record with such id in table
        }

        return $this->typecastLoadRow($m, $this->data[$table][$id]);
    }

    /**
     * Tries to load first available record and return data record.
     * Doesn't throw exception if model can't be loaded or there are no data records.
     *
     * @param Model $m
     * @param mixed $table
     *
     * @return array|false
     */
    public function tryLoadAny(Model $m, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        if (!$this->data[$table]) {
            return false; // no records at all in table
        }

        reset($this->data[$table]);
        $key = key($this->data[$table]);

        $row = $this->load($m, $key, $table);
        $m->id = $key;

        return $row;
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @param Model  $m
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function insert(Model $m, $data, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $data = $this->typecastSaveRow($m, $data);

        $id = $this->generateNewID($m, $table);
        $data[$m->id_field] = $id;
        $this->data[$table][$id] = $data;

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function update(Model $m, $id, $data, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $data = $this->typecastSaveRow($m, $data);

        $this->data[$table][$id] =
            array_merge(
                isset($this->data[$table][$id]) ? $this->data[$table][$id] : [],
                $data
            );

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     */
    public function delete(Model $m, $id, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        unset($this->data[$table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @param Model  $m
     * @param string $table
     *
     * @return string
     */
    public function generateNewID($m, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $ids = array_keys($this->data[$table]);

        $type = $m->getElement($m->id_field)->type;

        switch ($type) {
            case 'integer':
                return count($ids) === 0 ? 1 : (max($ids) + 1);
            case 'string':
                return uniqid();
            default:
                throw new Exception([
                    'Unsupported id field type. Array supports type=integer or type=string only',
                    'type' => $type,
                ]);
        }
    }

    public function prepareIterator(Model $m)
    {
        return $this->data[$m->table];
    }
}
