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

    public function getRawDataIterator(Model $model): \Iterator
    {
        return (function ($iterator) use ($model) {
            foreach ($iterator as $id => $row) {
                yield $id => $this->getRowWithId($model, $row, $id);
            }
        })(new \ArrayIterator($this->data[$model->table]));
    }

    public function setRawData(Model $model, array $row, $id = null)
    {
        $row = $this->getRowWithId($model, $row, $id);

        $id = $id ?? $this->lastInsertId($model);

        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->getPersistenceName();

            unset($row[$idColumnName]);
        }

        $this->data[$model->table][$id] = $row; //array_intersect_key($row, $rowWithId);

        return $id;
    }

    public function unsetRawData(string $table, $id)
    {
        unset($this->data[$table][$id]);
    }

    private function getRowWithId(Model $model, array $row, $id = null)
    {
        if ($id === null) {
            $id = $this->generateNewId($model);
        }

        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->getPersistenceName();

            if (array_key_exists($idColumnName, $row)) {
                $this->assertNoIdMismatch($row[$idColumnName], $id);
                unset($row[$idColumnName]);
            }

            // typecastSave value so we can use strict comparison
            $row = [$idColumnName => $this->typecastSaveField($idField, $id)] + $row;
        }

        return $row;
    }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row constains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
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
     * Tries to load first available record and return data record.
     */
    public function loadAny(Model $model, string $table = null): ?array
    {
        $row = $this->tryLoadAny($model, $table);
        if ($row === null) {
            throw (new Exception('No matching records were found', 404))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        return $row;
    }

    /**
     * Generates new record ID.
     *
     * @return string
     */
    public function generateNewId(Model $model, string $table = null)
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

        $this->lastInsertIds[$table] = $id;

        return $id;
    }

    public function lastInsertId(Model $model): string
    {
        return (string) ($this->lastInsertIds[$model->table] ?? '');
    }

    public function query(Model $model): AbstractQuery
    {
        return new Array_\Query($model, $this);
    }
}
