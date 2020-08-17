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

    public function getRawDataIterator($table): \Iterator
    {
        return new \ArrayIterator($this->data[$table]);
    }

    public function setRawData(Model $model, $data, $id = null)
    {
        if ($id === null) {
            $id = $this->generateNewId($model);

            if ($model->id_field) {
                $data[$model->id_field] = $id;
            }
        }

        $this->data[$model->table][$id] = $data;

        return $id;
    }

    public function unsetRawData(string $table, $id)
    {
        unset($this->data[$table][$id]);
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

    public function query(Model $model): AbstractQuery
    {
        return new Array_\Query($model, $this);
    }
}
