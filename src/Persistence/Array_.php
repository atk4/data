<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Persistence;

use atk4\data\Exception;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Implements persistence driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Array_ extends Persistence
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
            '_default_seed_join' => \atk4\data\Join\Array_::class,
        ], $defaults);

        $m = parent::add($m, $defaults);

        if ($f = $m->hasField($m->id_field)) {
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
        if ($m->id_field) {
            $data[$m->id_field] = $id;
        }
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
                $this->data[$table][$id] ?? [],
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

        if ($m->id_field) {
            $ids = array_keys($this->data[$table]);
            $type = $m->getField($m->id_field)->type;
        } else {
            $ids = [count($this->data[$table])]; // use ids starting from 1
            $type = 'integer';
        }

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

    /**
     * Prepare iterator.
     *
     * @param Model $m
     *
     * @return array
     */
    public function prepareIterator(Model $m)
    {
        return $m->action('select')->get();
    }

    /**
     * Export all DataSet.
     *
     * @param Model      $m
     * @param array|null $fields
     * @param bool       $typecast_data Should we typecast exported data
     *
     * @return array
     */
    public function export(Model $m, $fields = null, $typecast_data = true)
    {
        $data = $m->action('select', [$fields])->get();

        if ($typecast_data) {
            $data = array_map(function ($r) use ($m) {
                return $this->typecastLoadRow($m, $r);
            }, $data);
        }

        return $data;
    }

    /**
     * Typecast data and return Iterator of data array.
     *
     * @param Model $m
     * @param array $fields
     *
     * @return \atk4\data\Action\Iterator
     */
    public function initAction(Model $m, $fields = null)
    {
        $keys = $fields ? array_flip($fields) : null;

        $data = array_map(function ($r) use ($m, $keys) {
            // typecasting moved to export() method
            //return $this->typecastLoadRow($m, $keys ? array_intersect_key($r, $keys) : $r);
            return $keys ? array_intersect_key($r, $keys) : $r;
        }, $this->data[$m->table]);

        return new \atk4\data\Action\Iterator($data);
    }

    /**
     * Will set limit defined inside $m onto data.
     *
     * @param Model         $m
     * @param ArrayIterator $action
     */
    protected function setLimitOrder($m, &$action)
    {
        // first order by
        if ($m->order) {
            $action->order($m->order);
        }

        // then set limit
        if ($m->limit && ($m->limit[0] || $m->limit[1])) {
            $cnt = $m->limit[0] ?? 0;
            $shift = $m->limit[1] ?? 0;

            $action->limit($cnt, $shift);
        }
    }

    /**
     * Will apply conditions defined inside $m onto query $q.
     *
     * @param Model                      $m
     * @param \atk4\data\Action\Iterator $q
     *
     * @return \atk4\data\Action\Iterator|null
     */
    public function applyConditions(Model $model, \atk4\data\Action\Iterator $iterator)
    {
        if (empty($model->conditions)) {
            // no conditions are set in the model
            return $iterator;
        }

        foreach ($model->conditions as $cond) {

            // assume the action is "where" if we have only 2 parameters
            if (count($cond) == 2) {
                array_splice($cond, -1, 1, ['where', $cond[1]]);
            }

            // condition must have 3 params at this point
            if (count($cond) != 3) {
                // condition can have up to three params
                throw new Exception([
                    'Persistence\Array_ driver condition unsupported format',
                    'reason'   => 'condition can have two to three params',
                    'condition'=> $cond,
                ]);
            }

            // extract
            $field = $cond[0];
            $method = strtolower($cond[1]);
            $value = $cond[2];

            // check if the method is supported by the iterator
            if (!method_exists($iterator, $method)) {
                throw new Exception([
                    'Persistence\Array_ driver condition unsupported method',
                    'reason'    => "method $method not implemented for Action\Iterator",
                    'condition' => $cond,
                ]);
            }

            // get the model field
            if (is_string($field)) {
                $field = $model->getField($field);
            }

            if (!is_a($field, Field::class)) {
                throw new Exception([
                    'Persistence\Array_ driver condition unsupported format',
                    'reason'    => 'Unsupported object instance '.get_class($field),
                    'condition' => $cond,
                ]);
            }

            // get the field name
            $short_name = $field->short_name;
            // .. the value
            $value = $this->typecastSaveField($field, $value);
            // run the (filter) method
            $iterator->{$method}($short_name, $value);
        }
    }

    /**
     * Various actions possible here, mostly for compatibility with SQLs.
     *
     * @param Model  $m
     * @param string $type
     * @param array  $args
     *
     * @return mixed
     */
    public function action($m, $type, $args = [])
    {
        if (!is_array($args)) {
            throw new Exception([
                '$args must be an array',
                'args' => $args,
            ]);
        }

        switch ($type) {
            case 'select':
                $action = $this->initAction($m, $args[0] ?? null);
                $this->applyConditions($m, $action);
                $this->setLimitOrder($m, $action);

                return $action;

            case 'count':
                $action = $this->initAction($m, $args[0] ?? null);
                $this->applyConditions($m, $action);
                $this->setLimitOrder($m, $action);

                return $action->count();

            case 'field':
                if (!isset($args[0])) {
                    throw new Exception([
                        'This action requires one argument with field name',
                        'action' => $type,
                    ]);
                }

                $field = is_string($args[0]) ? $args[0] : $args[0][0];

                $action = $this->initAction($m, [$field]);
                $this->applyConditions($m, $action);
                $this->setLimitOrder($m, $action);

                // get first record
                $row = $action->getRow();
                if ($row) {
                    if (isset($args['alias']) && array_key_exists($field, $row)) {
                        $row[$args['alias']] = $row[$field];
                        unset($row[$field]);
                    }
                }

                return $row;

            /* These are not implemented yet
            case 'fx':
            case 'fx0':

                return $action->aggregate($field->short_name, $fx);
            */

            default:
                throw new Exception([
                    'Unsupported action mode',
                    'type' => $type,
                ]);
        }
    }
}
