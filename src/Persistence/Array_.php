<?php

declare(strict_types=1);

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
    /** @var array */
    private $data;

    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Array of last inserted ids per table.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @var array
     */
    protected $lastInsertIds = [];

    /**
     * @deprecated TODO temporary for these:
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsOne.php#L119
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsMany.php#L66
     *             remove once fixed/no longer needed
     */
    public function getRawDataByTable(string $table): array
    {
        return $this->data[$table];
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
            '_default_seed_join' => [Array_\Join::class],
        ], $defaults);

        $model = parent::add($model, $defaults);

        if ($model->id_field && $model->hasField($model->id_field)) {
            $f = $model->getField($model->id_field);
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
     * @param mixed $id
     */
    public function load(Model $model, $id, string $table = null): array
    {
        if (isset($model->table) && !isset($this->data[$model->table])) {
            throw (new Exception('Table was not found in the array data source'))
                ->addMoreInfo('table', $model->table);
        }

        if (!isset($this->data[$table ?? $model->table][$id])) {
            throw (new Exception('Record with specified ID was not found', 404))
                ->addMoreInfo('id', $id);
        }

        return $this->tryLoad($model, $id, $table);
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param mixed $id
     */
    public function tryLoad(Model $model, $id, string $table = null): ?array
    {
        $table = $table ?? $model->table;

        if (!isset($this->data[$table][$id])) {
            return null;
        }

        return $this->typecastLoadRow($model, $this->data[$table][$id]);
    }

    /**
     * Tries to load first available record and return data record.
     * Doesn't throw exception if model can't be loaded or there are no data records.
     *
     * @param mixed $table
     */
    public function tryLoadAny(Model $model, string $table = null): ?array
    {
        $table = $table ?? $model->table;

        if (!$this->data[$table]) {
            return null;
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
     * @param array $data
     *
     * @return mixed
     */
    public function insert(Model $model, $data, string $table = null)
    {
        $table = $table ?? $model->table;

        $data = $this->typecastSaveRow($model, $data);

        $id = $this->generateNewId($model, $table);
        if ($model->id_field) {
            $data[$model->id_field] = $id;
        }
        $this->data[$table][$id] = $data;

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param mixed $id
     * @param array $data
     *
     * @return mixed
     */
    public function update(Model $model, $id, $data, string $table = null)
    {
        $table = $table ?? $model->table;

        $data = $this->typecastSaveRow($model, $data);

        $this->data[$table][$id] = array_merge($this->data[$table][$id] ?? [], $data);

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id, string $table = null)
    {
        $table = $table ?? $model->table;

        unset($this->data[$table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @param Model $model
     *
     * @return string
     */
    public function generateNewId($model, string $table = null)
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
     */
    public function prepareIterator(Model $model): iterable
    {
        return $model->toQuery('select')->get();
    }

    /**
     * Export all DataSet.
     *
     * @param bool $typecast_data Should we typecast exported data
     */
    public function export(Model $model, array $fields = null, $typecast = true): array
    {
        $data = $model->toQuery('select', [$fields])->get();

        if ($typecast) {
            $data = array_map(function ($row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    protected function initQuery(Model $model): AbstractQuery
    {
        return new Array_\Query($model);
    }
}
