<?php


namespace atk4\data;


class Persistence_JSON extends Persistence
{

    /**
     * @var array Contains decoded JSON data. If model specifies table, then it is loaded from a specific
     * route, e.g. $table='user/blah/abc' will load $data['user']['blah']['abc']
     *
     * Table route can include ID numbers for record traversal 'user/23/Roles'
     */
    public $data;

    /**
     * Decode and store $json as associative array.
     *
     * @param $json
     */
    public function __construct($json)
    {
        $this->data = json_decode($json, true);
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $m Model which will use this persistence
     * @param array $defaults Properties
     *
     * @return Model
     * @throws Exception
     */
    public function add($m, $defaults = [])
    {
        if (isset($defaults[0])) {
            $m->table = $defaults[0];
            unset($defaults[0]);
        }

        $m = parent::add($m, $defaults);

        return $m;
    }

    /**
     * When specified a path, such as 'foo/bar/baz', this will locate a specific spot inside $this->data
     * and return reference to it.
     *
     * @param $path
     */
    public function getDataRef($path)
    {
        $path_array = explode('/', $path);

        $cur =& $this->data;

        foreach($path_array as $node) {
            if (!isset($cur[$node])) {
                throw new Exception([
                    'Path not found in JSON',
                    'path'=>$path
                ]);
            }

            $cur =& $cur[$node];
        }

    }

    /**
     * Loads model and returns data record.
     *
     * @param Model $m
     * @param mixed $id
     * @param string $table
     *
     * @return array|false
     * @throws Exception
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


}