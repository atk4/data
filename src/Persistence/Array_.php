<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Array_\Action;
use Atk4\Data\Persistence\Array_\Action\RenameColumnIterator;
use Atk4\Data\Persistence\Array_\Db\Row;
use Atk4\Data\Persistence\Array_\Db\Table;

/**
 * Implements persistence driver that can save data into array and load
 * from array. This basic driver only offers the load/save support based
 * around ID, you can't use conditions, order or limit.
 */
class Array_ extends Persistence
{
    /** @var array */
    private $seedData;

    /** @var array<string, Table> */
    private $data;

    /** @var array<string, int> */
    protected $maxSeenIdByTable = [];

    /** @var array<string, int|string> */
    protected $lastInsertIdByTable = [];

    /** @var string */
    protected $lastInsertIdTable;

    public function __construct(array $data = [])
    {
        $this->seedData = $data;

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there 1/2
        if (count($this->seedData) > 0 && !isset($this->seedData['data'])) {
            $rowSample = reset($this->seedData);
            if (is_array($rowSample) && $rowSample !== [] && !is_array(reset($rowSample))) {
                $this->seedData = ['data' => $this->seedData];
            }
        }
    }

    private function seedData(Model $model): void
    {
        $tableName = $model->table;
        if (isset($this->data[$tableName])) {
            return;
        }

        $this->data[$tableName] = new Table($tableName);

        if (isset($this->seedData[$tableName])) {
            $rows = $this->seedData[$tableName];
            unset($this->seedData[$tableName]);

            foreach ($rows as $id => $row) {
                $this->saveRow($model, $row, $id);
            }
        }

        // for array persistence join which accept table directly (without model initialization)
        foreach ($model->getFields() as $field) {
            if ($field->hasJoin()) {
                $join = $field->getJoin();
                $joinTableName = \Closure::bind(function () use ($join) {
                    return $join->foreign_table;
                }, null, Array_\Join::class)();
                if (isset($this->seedData[$joinTableName])) {
                    $dummyJoinModel = new Model($this, ['table' => $joinTableName]);
                    $this->add($dummyJoinModel);
                }
            }
        }
    }

    private function seedDataAndGetTable(Model $model): Table
    {
        $this->seedData($model);

        return $this->data[$model->table];
    }

    /**
     * @deprecated TODO temporary for these:
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsOne.php#L119
     *             - https://github.com/atk4/data/blob/90ab68ac063b8fc2c72dcd66115f1bd3f70a3a92/src/Reference/ContainsMany.php#L66
     *             remove once fixed/no longer needed
     */
    public function getRawDataByTable(Model $model, string $table): array
    {
        if (!is_object($model->table)) {
            $this->seedData($model);
        }

        $rows = [];
        foreach ($this->data[$table]->getRows() as $row) {
            $rows[$row->getValue($model->id_field)] = $row->getData();
        }

        return $rows;
    }

    /**
     * @param int|string|null $idFromRow
     * @param int|string      $id
     */
    private function assertNoIdMismatch(Model $model, $idFromRow, $id): void
    {
        if ($idFromRow !== null && !$model->getField($model->id_field)->compare($idFromRow, $id)) {
            throw (new Exception('Row constains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $id)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    /**
     * @param mixed $id
     */
    private function saveRow(Model $model, array $rowData, $id): void
    {
        if ($model->id_field) {
            $idField = $model->getField($model->id_field);
            $id = $idField->normalize($id);
            $idColumnName = $idField->getPersistenceName();
            if (array_key_exists($idColumnName, $rowData)) {
                $this->assertNoIdMismatch($model, $rowData[$idColumnName], $id);
                unset($rowData[$idColumnName]);
            }

            $rowData = [$idColumnName => $id] + $rowData;
        }

        if ($id > ($this->maxSeenIdByTable[$model->table] ?? 0)) {
            $this->maxSeenIdByTable[$model->table] = $id;
        }

        $table = $this->data[$model->table];

        $row = $table->getRowById($model, $id);
        if ($row !== null) {
            foreach (array_keys($rowData) as $columnName) {
                if (!$table->hasColumnName($columnName)) {
                    $table->addColumnName($columnName);
                }
            }
            $row->updateValues($rowData);
        } else {
            $row = $table->addRow(Row::class, $rowData);
        }
    }

    public function add(Model $model, array $defaults = []): void
    {
        if (isset($defaults[0])) {
            $model->table = $defaults[0];
            unset($defaults[0]);
        }

        $defaults = array_merge([
            '_default_seed_join' => [Array_\Join::class],
        ], $defaults);

        parent::add($model, $defaults);

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there 2/2
        if (!$model->table) {
            $model->table = 'data';
        }

        if ($model->id_field && $model->hasField($model->id_field)) {
            $f = $model->getField($model->id_field);
            if ($f->type === null) {
                $f->type = 'integer';
            }
        }

        if (!is_object($model->table)) {
            $this->seedData($model);
        }
    }

    private function getPersistenceNameToNameMap(Model $model): array
    {
        return array_flip(array_map(fn (Field $f) => $f->getPersistenceName(), $model->getFields()));
    }

    private function filterRowDataOnlyModelFields(Model $model, array $rowDataRaw): array
    {
        return array_intersect_key($rowDataRaw, $this->getPersistenceNameToNameMap($model));
    }

    public function remapLoadRow(Model $model, array $row): array
    {
        $rowRemapped = [];
        $map = $this->getPersistenceNameToNameMap($model);
        foreach ($row as $k => $v) {
            $rowRemapped[$map[$k]] = $v;
        }

        return $rowRemapped;
    }

    public function tryLoad(Model $model, $id): ?array
    {
        if ($id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY) {
            $action = $this->action($model, 'select');
            $action->generator->rewind(); // TODO needed for some reasons!

            $selectRow = $action->getRow();
            if ($selectRow === null) {
                return null;
            } elseif ($id === self::ID_LOAD_ONE && $action->getRow() !== null) {
                throw (new Exception('Ambiguous conditions, more than one record can be loaded.'))
                    ->addMoreInfo('model', $model)
                    ->addMoreInfo('id', null);
            }

            $id = $selectRow[$model->id_field];

            $row = $this->tryLoad($model, $id);

            return $row;
        }

        if (is_object($model->table)) {
            $action = $this->action($model, 'select');
            $condition = new Model\Scope\Condition('', $id);
            $condition->key = $model->getField($model->id_field);
            $action->filter($condition);
            $action->generator->rewind(); // TODO needed for some reasons!

            $rowData = $action->getRow();
            if ($rowData === null) {
                return null;
            }
        } else {
            $table = $this->seedDataAndGetTable($model);

            $row = $table->getRowById($model, $id);
            if ($row === null) {
                return null;
            }

            $rowData = $this->remapLoadRow($model, $this->filterRowDataOnlyModelFields($model, $row->getData()));
        }

        return $this->typecastLoadRow($model, $rowData);
    }

    protected function insertRaw(Model $model, array $dataRaw)
    {
        $this->seedData($model);

        $idRaw = $dataRaw[$model->id_field] ?? $this->generateNewId($model);

        $this->saveRow($model, $dataRaw, $idRaw);

        return $idRaw;
    }

    protected function updateRaw(Model $model, $idRaw, array $dataRaw): void
    {
        $table = $this->seedDataAndGetTable($model);

        $this->saveRow($model, array_merge($this->filterRowDataOnlyModelFields($model, $table->getRowById($model, $idRaw)->getData()), $dataRaw), $idRaw);
    }

    protected function deleteRaw(Model $model, $idRaw): void
    {
        $table = $this->seedDataAndGetTable($model);

        $table->deleteRow($table->getRowById($model, $idRaw));
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
                $nextId = ($this->maxSeenIdByTable[$model->table] ?? 0) + 1;
                $this->maxSeenIdByTable[$model->table] = $nextId;

                break;
            case 'string':
                $nextId = uniqid();

                break;
            default:
                throw (new Exception('Unsupported id field type. Array supports type=integer or type=string only'))
                    ->addMoreInfo('type', $type);
        }

        $this->lastInsertIdByTable[$model->table] = $nextId;
        $this->lastInsertIdTable = $model->table;

        return $nextId;
    }

    /**
     * Last ID inserted.
     *
     * @return mixed
     */
    public function lastInsertId(Model $model = null)
    {
        if ($model) {
            return $this->lastInsertIdByTable[$model->table] ?? null;
        }

        return $this->lastInsertIdByTable[$this->lastInsertIdTable] ?? null;
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
     * Typecast data and return Action of data array.
     */
    public function initAction(Model $model, array $fields = null): Action
    {
        if (is_object($model->table)) {
            $tableAction = $this->action($model->table, 'select');

            $rows = [];
            foreach ($tableAction->getRows() as $k => $row) {
                $rows[$k] = $this->filterRowDataOnlyModelFields($model, $row);
            }
        } else {
            $table = $this->seedDataAndGetTable($model);

            $rows = [];
            foreach ($table->getRows() as $row) {
                $rows[$row->getValue($model->getField($model->id_field)->getPersistenceName())] = $this->filterRowDataOnlyModelFields($model, $row->getData());
            }
        }

        foreach ($rows as $rowIndex => $row) {
            $rows[$rowIndex] = $this->remapLoadRow($model, $row);
        }

        if ($fields !== null) {
            $rows = array_map(function ($row) use ($fields) {
                return array_intersect_key($row, array_flip($fields));
            }, $rows);
        }

        return new Action($rows);
    }

    /**
     * Will set limit defined inside $model onto Action.
     */
    protected function setLimitOrder(Model $model, Action $action): void
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
     * Will apply conditions defined inside $model onto Action.
     */
    protected function applyScope(Model $model, Action $action): void
    {
        $scope = $model->getModel(true)->scope();

        // add entity ID to scope to allow easy traversal
        if ($model->isEntity() && $model->id_field && $model->getId() !== null) {
            $scope = new Model\Scope([$scope]);
            $scope->addCondition($model->getField($model->id_field), $model->getId());
        }

        $action->filter($scope);
    }

    /**
     * Various actions possible here, mostly for compatibility with SQLs.
     *
     * @return mixed
     */
    public function action(Model $model, string $type, array $args = [])
    {
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

                if (isset($args['alias'])) {
                    $action->generator = new RenameColumnIterator($action->generator, $field, $args['alias']);
                }

                return $action;
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
