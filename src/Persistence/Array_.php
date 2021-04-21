<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * Implements persistence driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Array_ extends Persistence
{
    /** @var array */
    private $seedData;

    /** @var array<string, array> */
    private $data;

    /**
     * Array of last inserted ids per table.
     * Last inserted ID for any table is stored under '$' key.
     *
     * @var array
     */
    protected $lastInsertIds = [];

    public function __construct(array $data = [])
    {
        $this->seedData = $data;


        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there 1/2
        if (count($this->seedData) > 0 && !isset($this->seedData['data'])) {
            $rowSample = reset($this->seedData);
            if (is_array($rowSample) && !is_array(reset($rowSample))) {
                $this->seedData = ['data' => $this->seedData];
            }
        }
    }

    private function seedData(Model $model): void
    {
        if (isset($this->data[$model->table])) {
            return;
        }

        $this->data[$model->table] = [];

        if (isset($this->seedData[$model->table])) {
            $rows = $this->seedData[$model->table];
            unset($this->seedData[$model->table]);

            foreach ($rows as $id => $row) {
                $this->saveRow($model, $row, $id);
            }
        }

        // for array persistence join which accept table directly (without model initialization)
        foreach ($model->getFields() as $field) {
            if ($field->hasJoin()) {
                $join = $field->getJoin();
                $joinTable = \Closure::bind(function () use ($join) {
                    return $join->foreign_table;
                }, null, Array_\Join::class)();
                if (isset($this->seedData[$joinTable])) {
                    $dummyJoinModel = new Model($this, ['table' => $joinTable]);
                    $this->add($dummyJoinModel);
                }
            }
        }
    }

    /**
     * @deprecated TODO temporary for these:
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsOne.php#L119
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsMany.php#L66
     *             remove once fixed/no longer needed
     */
    public function getRawDataByTable(Model $model, string $table): array
    {
        $this->seedData($model);

        $rows = [];
        foreach ($this->data[$table] as $id => $row) {
            $this->addIdToLoadRow($model, $row, $id);
            $rows[$id] = $row;
        }

        return $rows;
    }

    private function assertNoIdMismatch($idFromRow, $id): void
    {
        if ($idFromRow !== null && (is_int($idFromRow) ? (string) $idFromRow : $idFromRow) !== (is_int($id) ? (string) $id : $id)) {
            throw (new Exception('Row constains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    private function saveRow(Model $model, array $row, $id): void
    {
        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->getPersistenceName();
            if (array_key_exists($idColumnName, $row)) {
                $this->assertNoIdMismatch($row[$idColumnName], $id);
                unset($row[$idColumnName]);
            }
        }

        $this->data[$model->table][$id] = $row;
    }

    private function addIdToLoadRow(Model $model, array &$row, $id): void
    {
        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $idColumnName = $idField->getPersistenceName();
            if (array_key_exists($idColumnName, $row)) {
                $this->assertNoIdMismatch($row[$idColumnName], $id);
            }

            $row = [$idColumnName => $id] + $row;
        }
    }

    public function typecastSaveRow(Model $model, array $row): array
    {
        $sqlPersistence = (new \ReflectionClass(Sql::class))->newInstanceWithoutConstructor();

        return $sqlPersistence->typecastSaveRow($model, $row);
    }

    public function typecastLoadRow(Model $model, array $row): array
    {
        $sqlPersistence = (new \ReflectionClass(Sql::class))->newInstanceWithoutConstructor();

        return $sqlPersistence->typecastLoadRow($model, $row);
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

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there 2/2
        if (!$model->table) {
            $model->table = 'data';
        }

        if ($model->id_field && $model->hasField($model->id_field)) {
            $f = $model->getField($model->id_field);
            if (!$f->type) {
                $f->type = 'integer';
            }
        }

        $this->seedData($model);

        return $model;
    }

    public function tryLoad(Model $model, $id): ?array
    {
        $this->seedData($model);

        if (!isset($this->data[$model->table])) {
            throw (new Exception('Table was not found in the array data source'))
                ->addMoreInfo('table', $model->table);
        }

        if ($id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY) {
            if (count($this->data[$model->table]) === 0) {
                return null;
            } elseif ($id === self::ID_LOAD_ONE && count($this->data[$model->table]) !== 1) {
                throw (new Exception('Ambiguous conditions, more than one record can be loaded.'))
                    ->addMoreInfo('model', $model)
                    ->addMoreInfo('id', null);
            }

            $id = array_key_first($this->data[$model->table]);

            $row = $this->tryLoad($model, $id);
            $model->setId($id); // @TODO is it needed?

            return $row;
        }

        if (!isset($this->data[$model->table][$id])) {
            return null;
        }

        $row = $this->data[$model->table][$id];
        $this->addIdToLoadRow($model, $row, $id);

        return $this->typecastLoadRow($model, $row);
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @return mixed
     */
    public function insert(Model $model, array $data)
    {
        $this->seedData($model);

        $data = $this->typecastSaveRow($model, $data);

        $id = $data[$model->id_field] ?? $this->generateNewId($model);

        $this->saveRow($model, $data, $id);

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param mixed $id
     *
     * @return mixed
     */
    public function update(Model $model, $id, array $data)
    {
        $this->seedData($model);

        $data = $this->typecastSaveRow($model, $data);

        $this->saveRow($model, array_merge($this->data[$model->table][$id] ?? [], $data), $id);

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param mixed $id
     */
    public function delete(Model $model, $id)
    {
        $this->seedData($model);

        unset($this->data[$model->table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @return string
     */
    public function generateNewId(Model $model)
    {
        $this->seedData($model);

        $type = $model->id_field ? $model->getField($model->id_field)->type : 'integer';

        switch ($type) {
            case 'integer':
                $ids = $model->id_field ? array_keys($this->data[$model->table]) : [count($this->data[$model->table])];

                $id = $ids ? max($ids) + 1 : 1;

                break;
            case 'string':
                $id = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        $this->lastInsertIds[$model->table] = $id;
        $this->lastInsertIds['$'] = $id;

        return $id;
    }

    /**
     * Last ID inserted.
     * Last inserted ID for any table is stored under '$' key.
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

    public function prepareIterator(Model $model): \Traversable
    {
        return $model->action('select')->generator; // @phpstan-ignore-line
    }

    /**
     * Export all DataSet.
     */
    public function export(Model $model, array $fields = null, bool $typecast = true): array
    {
        $data = $model->action('select', [$fields])->getRows();

        if ($typecast) {
            $data = array_map(function ($row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    /**
     * Typecast data and return Iterator of data array.
     */
    public function initAction(Model $model, array $fields = null): \Atk4\Data\Action\Iterator
    {
        $this->seedData($model);

        $data = $this->data[$model->table];
        array_walk($data, function (&$row, $id) use ($model) {
            $this->addIdToLoadRow($model, $row, $id);
        });

        if ($fields !== null) {
            $data = array_map(function ($row) use ($fields) {
                return array_intersect_key($row, array_flip($fields));
            }, $data);
        }

        return new \Atk4\Data\Action\Iterator($data);
    }

    /**
     * Will set limit defined inside $model onto data.
     */
    protected function setLimitOrder(Model $model, \Atk4\Data\Action\Iterator $action)
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
     * @return \Atk4\Data\Action\Iterator|null
     */
    public function applyScope(Model $model, \Atk4\Data\Action\Iterator $iterator)
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

                [$fx, $field] = $args;

                $action = $this->initAction($model, [$field]);
                $this->applyScope($model, $action);
                $this->setLimitOrder($model, $action);

                return $action->aggregate($fx, $field, $type === 'fx0');
            default:
                throw (new Exception('Unsupported action mode'))
                    ->addMoreInfo('type', $type);
        }
    }
}
