<?php

namespace atk4\data\Persistence;

use atk4\data\Exception;
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
     * Array of last inserted ids per table.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @var array
     */
    protected $lastInsertIds = [];

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
     * {@inheritdoc}
     */
    public function add(Model $model, array $defaults = []): Model
    {
        if (isset($defaults[0])) {
            $model->table = $defaults[0];
            unset($defaults[0]);
        }

        $defaults = array_merge([
            '_default_seed_join' => \atk4\data\Join\Array_::class,
        ], $defaults);

        $model = parent::add($model, $defaults);

        if ($f = $model->hasField($model->id_field)) {
            if (!$f->type) {
                $f->type = 'integer';
            }
        }

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there
        if (!$model->table) {
            $model->table = 'data'; // fake table name 'data'
            if (!isset($this->data[$model->table]) || count($this->data) !== 1) {
                $this->data = [$model->table => $this->data];
            }
        }

        // if there is no such table in persistence, then create empty one
        if (!isset($this->data[$model->table])) {
            $this->data[$model->table] = [];
        }

        return $model;
    }

    /**
     * Loads model and returns data record.
     *
     * @param mixed  $id
     * @param string $table
     *
     * @return array|false
     */
    public function load(Model $model, $id, $table = null)
    {
        if (isset($model->table) && !isset($this->data[$model->table])) {
            throw (new Exception('Table was not found in the array data source'))
                ->addMoreInfo('table', $model->table);
        }

        if (!isset($this->data[$table ?: $model->table][$id])) {
            throw (new Exception('Record with specified ID was not found', 404))
                ->addMoreInfo('id', $id);
        }

        return $this->tryLoad($model, $id, $table);
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param mixed  $id
     * @param string $table
     *
     * @return array|false
     */
    public function tryLoad(Model $model, $id, $table = null)
    {
        $table = $table ?? $model->table;

        if (!isset($this->data[$table][$id])) {
            return false; // no record with such id in table
        }

        return $this->typecastLoadRow($model, $this->data[$table][$id]);
    }

    /**
     * Tries to load first available record and return data record.
     * Doesn't throw exception if model can't be loaded or there are no data records.
     *
     * @param mixed $table
     *
     * @return array|false
     */
    public function tryLoadAny(Model $model, $table = null)
    {
        $table = $table ?? $model->table;

        if (!$this->data[$table]) {
            return false; // no records at all in table
        }

        reset($this->data[$table]);
        $id = key($this->data[$table]);

        $row = $this->load($model, $id, $table);
        $model->id = $id;

        return $row;
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function insert(Model $model, $data, $table = null)
    {
        $table = $table ?? $model->table;

        $data = $this->typecastSaveRow($model, $data);

        $id = $this->generateNewID($model, $table);
        if ($model->id_field) {
            $data[$model->id_field] = $id;
        }
        $this->data[$table][$id] = $data;

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param mixed  $id
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function update(Model $model, $id, $data, $table = null)
    {
        $table = $table ?? $model->table;

        $data = $this->typecastSaveRow($model, $data);

        $this->data[$table][$id] = array_merge($this->data[$table][$id] ?? [], $data);

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed  $id
     * @param string $table
     */
    public function delete(Model $model, $id, $table = null)
    {
        $table = $table ?? $model->table;

        unset($this->data[$table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @param Model  $model
     * @param string $table
     *
     * @return string
     */
    public function generateNewID($model, $table = null)
    {
        $table = $table ?? $model->table;

        $type = $model->id_field ? $model->getField($model->id_field)->type : 'integer';

        switch ($type) {
            case 'integer':
                $ids = $model->id_field ? array_keys($this->data[$table]) : [count($this->data[$table])];

                $id = $ids ? max($ids) + 1 : 1;

                break;
            case 'string':
                $id = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        return $this->lastInsertIds[$table] = $this->lastInsertIds['$'] = $id;
    }

    /**
     * Last ID inserted.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @param Model $model
     *
     * @return mixed
     */
    public function lastInsertId(Model $model = null)
    {
        if ($model) {
            return $this->lastInsertIds[$model->table] ?? null;
        }

        return $this->lastInsertIds['$'] ?? null;
    }

    /**
     * Prepare iterator.
     *
     * @return array
     */
    public function prepareIterator(Model $model)
    {
        return $model->action('select')->get();
    }

    /**
     * Export all DataSet.
     *
     * @param array|null $fields
     * @param bool       $typecast Should we typecast exported data
     *
     * @return array
     */
    public function export(Model $model, $fields = null, $typecast = true)
    {
        $data = $model->action('select', [$fields])->get();

        if ($typecast) {
            $data = array_map(function ($row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    /**
     * Typecast data and return Iterator of data array.
     *
     * @param array $fields
     *
     * @return \atk4\data\Action\Iterator
     */
    public function initAction(Model $model, $fields = null)
    {
        $data = $this->data[$model->table];

        if ($keys = array_flip((array) $fields)) {
            $data = array_map(function ($row) use ($model, $keys) {
                return array_intersect_key($row, $keys);
            }, $data);
        }

        return new \atk4\data\Action\Iterator($data);
    }

    /**
     * Will set limit defined inside $m onto data.
     *
     * @param \ArrayIterator $action
     */
    protected function setLimitOrder(Model $model, &$action)
    {
        // first order by
        if ($model->order) {
            $action->order($model->order);
        }

        // then set limit
        if ($model->limit && ($model->limit[0] || $model->limit[1])) {
            $action->limit($model->limit[0] ?? 0, $model->limit[1] ?? 0);
        }
    }

    /**
     * Will apply conditions defined inside $model onto $iterator.
     *
     * @return \atk4\data\Action\Iterator|null
     */
    public function applyScope(Model $model, \atk4\data\Action\Iterator $iterator)
    {
        return $iterator->filter($model->scope());
    }

    /**
     * Various actions possible here, mostly for compatibility with SQLs.
     *
     * @param string $type
     * @param array  $args
     *
     * @return mixed
     */
    public function action(Model $model, $type, $args = [])
    {
        $args = (array) $args;

        switch ($type) {
            case 'select':
                $action = $this->initAction($model, $args[0] ?? null);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action;
            case 'count':
                $action = $this->initAction($model, $args[0] ?? null);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action->count();
            case 'exists':
                $action = $this->initAction($model, $args[0] ?? null);
                $this->applyScope($model, $action);

                return $action->exists();
            case 'field':
                if (!isset($args[0])) {
                    throw (new Exception('This action requires one argument with field name'))
                        ->addMoreInfo('action', $type);
                }

                $field = is_string($args[0]) ? $args[0] : $args[0][0];

                $action = $this->initAction($model, [$field]);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                // get first record
                if ($row = $action->getRow()) {
                    if (isset($args['alias']) && array_key_exists($field, $row)) {
                        $row[$args['alias']] = $row[$field];
                        unset($row[$field]);
                    }
                }

                return $row;
            case 'fx':
            case 'fx0':
                if (!isset($args[0], $args[1])) {
                    throw (new Exception('fx action needs 2 arguments, eg: ["sum", "amount"]'))
                        ->addMoreInfo('action', $type);
                }

                $fx = $args[0];
                $field = $args[1] ?? null;
                $action = $this->initAction($model, $args[1] ?? null);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action->aggregate($fx, $field, $type == 'fx0');
            default:
                throw (new Exception('Unsupported action mode'))
                    ->addMoreInfo('type', $type);
        }
    }
}
