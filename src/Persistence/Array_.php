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
    /** @var array<string, array<int|string, mixed>> */
    private array $seedData;

    /** @var array<string, Table> */
    private array $data = [];

    /** @var array<string, int> */
    protected array $maxSeenIdByTable = [];

    /** @var array<string, int|string> */
    protected array $lastInsertIdByTable = [];

    protected string $lastInsertIdTable;

    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        $this->seedData = $data;

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there 1/2
        if (count($this->seedData) > 0 && !isset($this->seedData['data'])) {
            $rowSample = array_first($this->seedData);
            if (is_array($rowSample) && $rowSample !== [] && !is_array(array_first($rowSample))) {
                $this->seedData = ['data' => $this->seedData];
            }
        }
    }

    private function seedData(Model $model): void
    {
        $tableName = $model->table;

        $newTable = !isset($this->data[$tableName]);
        if ($newTable) {
            $this->data[$tableName] = new Table($tableName);
        }
        $table = $this->data[$tableName];

        foreach ($model->getFields() as $field) {
            if (!$field->neverPersist && !$field->hasJoin()) {
                $columnName = $field->getPersistenceName();
                if (!$table->hasColumn($columnName)) {
                    $table->addColumn($columnName);
                }
            }
        }

        if (!$newTable) {
            return;
        }

        assert(count($table->getColumnNames()) > 0);

        $rows = $this->seedData[$tableName] ?? [];
        unset($this->seedData[$tableName]);
        foreach ($rows as $idRaw => $row) {
            foreach ($row as $columnName => $svalue) {
                if (!$table->hasColumn($columnName)) {
                    $table->addColumn($columnName);
                }
            }

            $this->saveRow($model, $row, $idRaw, false);
        }
    }

    private function seedDataAndGetTable(Model $model): Table
    {
        $this->seedData($model);

        return $this->data[$model->table];
    }

    /**
     * @return array<mixed, array<string, mixed>>
     *
     * @deprecated TODO temporary for these:
     *             - https://github.com/atk4/data/blob/90ab68ac06/src/Reference/ContainsOne.php#L119
     *             - https://github.com/atk4/data/blob/90ab68ac06/src/Reference/ContainsMany.php#L66
     *             remove once fixed/no longer needed
     */
    public function getRawDataByTable(Model $model, string $table): array
    {
        $model->assertIsModel();

        if (!is_object($model->table)) {
            $this->seedData($model);
        }

        $rows = [];
        foreach ($this->data[$table]->getRows() as $row) {
            $rows[$row->getValue($model->getIdField()->getPersistenceName())] = $row->getData();
        }

        return $rows;
    }

    /**
     * @param int|string $idRaw
     *
     * @return int|string
     */
    private function normalizeIdRaw(Field $idField, $idRaw)
    {
        $id = $this->typecastLoadField($idField, $idRaw);

        return $this->typecastSaveField($idField, $id);
    }

    /**
     * @param int|string|null $idFromRow
     * @param int|string      $idRaw
     */
    private function assertNoIdMismatch(Field $idField, $idFromRow, $idRaw): void
    {
        if ($idFromRow !== null && $this->normalizeIdRaw($idField, $idFromRow) !== $this->normalizeIdRaw($idField, $idRaw)) {
            throw (new Exception('Row contains ID column, but it does not match the row ID'))
                ->addMoreInfo('idFromKey', $idRaw)
                ->addMoreInfo('idFromData', $idFromRow);
        }
    }

    /**
     * @param array<string, mixed> $rowData
     * @param mixed                $idRaw
     */
    private function saveRow(Model $model, array $rowData, $idRaw, bool $update): void
    {
        if ($model->idField) {
            $idField = $model->getIdField();
            $idRaw = $this->normalizeIdRaw($idField, $idRaw);
            $idColumnName = $idField->getPersistenceName();
            if (array_key_exists($idColumnName, $rowData)) {
                $this->assertNoIdMismatch($idField, $rowData[$idColumnName], $idRaw);
                unset($rowData[$idColumnName]);
            }

            $rowData = [$idColumnName => $idRaw] + $rowData;
        }

        if ($idRaw > ($this->maxSeenIdByTable[$model->table] ?? 0)) {
            $this->maxSeenIdByTable[$model->table] = $idRaw;
        }

        $table = $this->data[$model->table];

        $row = $table->getRowById($model, $idRaw);
        if ($row !== null) {
            if (!$update) {
                throw (new Exception('Row to insert has ID that already exists'))
                    ->addMoreInfo('model', $model)
                    ->addMoreInfo('id', $idRaw);
            }

            foreach (array_keys($rowData) as $columnName) {
                if (!$table->hasColumn($columnName)) {
                    $table->addColumn($columnName);
                }
            }
            $row->updateValues($rowData);
        } else {
            assert(!$update);

            $row = $table->addRow(Row::class, $rowData);
        }
    }

    #[\Override]
    public function add(Model $model, array $defaults = []): void
    {
        $defaults = array_merge([
            '_defaultSeedJoin' => [Array_\Join::class],
        ], $defaults);

        parent::add($model, $defaults);

        // if there is no model table specified, then create fake one named 'data'
        // and put all persistence data in there 2/2
        if (!$model->table) {
            $model->table = 'data';
        }

        if (!is_object($model->table)) {
            $this->seedData($model);
        }
    }

    /**
     * @return array<string, string>
     */
    private function getPersistenceNameToNameMap(Model $model): array
    {
        return array_flip(array_map(static fn (Field $f) => $f->getPersistenceName(), $model->getFields()));
    }

    /**
     * @param array<string, mixed> $rowDataRaw
     *
     * @return array<string, mixed>
     */
    private function filterRowDataOnlyModelFields(Model $model, array $rowDataRaw): array
    {
        return array_intersect_key($rowDataRaw, $this->getPersistenceNameToNameMap($model));
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array<string, mixed>
     */
    private function remapLoadRow(Model $model, array $row): array
    {
        $rowRemapped = [];
        $map = $this->getPersistenceNameToNameMap($model);
        foreach ($row as $k => $v) {
            $rowRemapped[$map[$k]] = $v;
        }

        return $rowRemapped;
    }

    #[\Override]
    public function tryLoad(Model $model, $id): ?array
    {
        $model->assertIsModel();

        if ($id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY) {
            $action = $this->action($model, 'select');

            $action->limit($id === self::ID_LOAD_ANY ? 1 : 2);

            $rowsRaw = $action->getRows();
            if (count($rowsRaw) === 0) {
                return null;
            } elseif (count($rowsRaw) !== 1) {
                throw (new Exception('Ambiguous conditions, more than one record can be loaded'))
                    ->addMoreInfo('model', $model)
                    ->addMoreInfo('id', null);
            }

            $idRaw = array_first($rowsRaw)[$model->idField];
            $id = $this->typecastLoadField($model->getIdField(), $idRaw);

            return $this->tryLoad($model, $id);
        }

        if (is_object($model->table)) {
            $action = $this->action($model, 'select');
            $condition = new Model\Scope\Condition($model->getIdField(), $id);
            $condition->setOwner($model->scope()); // needed for typecasting to apply
            $action->filter($condition);

            $rowData = $action->getRow();
            if ($rowData === null) {
                return null;
            }
        } else {
            $table = $this->seedDataAndGetTable($model);

            $idRaw = $this->typecastSaveField($model->getIdField(), $id);
            $row = $table->getRowById($model, $idRaw);
            if ($row === null) {
                return null;
            }

            $rowData = $this->remapLoadRow($model, $this->filterRowDataOnlyModelFields($model, $row->getData()));
        }

        return $this->typecastLoadRow($model, $rowData);
    }

    #[\Override]
    protected function insertRaw(Model $model, array $dataRaw)
    {
        $this->seedData($model);

        $idRaw = $dataRaw[$model->getIdField()->getPersistenceName()] ?? $this->generateNewId($model);

        $this->saveRow($model, $dataRaw, $idRaw, false);

        return $idRaw;
    }

    #[\Override]
    protected function updateRaw(Model $model, $idRaw, array $dataRaw): void
    {
        $table = $this->seedDataAndGetTable($model);

        $row = $table->getRowById($model, $idRaw);
        if ($row === null) {
            throw (new Exception('Row to update does not exist'))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('id', $idRaw);
        }

        $this->saveRow($model, array_merge($this->filterRowDataOnlyModelFields($model, $row->getData()), $dataRaw), $idRaw, true);
    }

    #[\Override]
    protected function deleteRaw(Model $model, $idRaw): void
    {
        $table = $this->seedDataAndGetTable($model);

        $table->deleteRow($table->getRowById($model, $idRaw));
    }

    /**
     * Generates new record ID.
     *
     * @return int|string
     */
    public function generateNewId(Model $model)
    {
        $this->seedData($model);

        $type = $model->idField
            ? $model->getIdField()->type
            : 'bigint';

        switch ($type) {
            case 'smallint':
            case 'integer':
            case 'bigint':
                $nextId = ($this->maxSeenIdByTable[$model->table] ?? 0) + 1;
                $this->maxSeenIdByTable[$model->table] = $nextId;

                break;
            case 'string':
                $nextId = uniqid();

                break;
            default:
                throw (new Exception('Unsupported ID field type'))
                    ->addMoreInfo('type', $type);
        }

        $this->lastInsertIdByTable[$model->table] = $nextId;
        $this->lastInsertIdTable = $model->table;

        return $nextId;
    }

    /**
     * Last ID inserted.
     *
     * @return int|string
     */
    public function lastInsertId(?Model $model = null)
    {
        if ($model !== null) {
            return $this->lastInsertIdByTable[$model->table] ?? null;
        }

        return $this->lastInsertIdByTable[$this->lastInsertIdTable] ?? null;
    }

    /**
     * @return \Traversable<array<string, mixed>>
     */
    public function prepareIterator(Model $model): \Traversable
    {
        return $model->action('select')->generator; // @phpstan-ignore property.notFound
    }

    /**
     * Export all DataSet.
     *
     * @param array<int, string>|null $fields
     *
     * @return list<array<string, mixed>>
     */
    public function export(Model $model, ?array $fields = null, bool $typecast = true): array
    {
        $data = $model->action('select', [$fields])->getRows();

        if ($typecast) {
            $data = array_map(function (array $row) use ($model) {
                return $this->typecastLoadRow($model, $row);
            }, $data);
        }

        return $data;
    }

    /**
     * Typecast data and return Action of data array.
     *
     * @param list<string>|null $fields
     */
    public function initAction(Model $model, ?array $fields = null): Action
    {
        if (is_object($model->table)) {
            $tableAction = $this->action($model->table, 'select');

            $rows = $tableAction->getRows();
            $columns = $tableAction->getColumns();
        } else {
            $table = $this->seedDataAndGetTable($model);

            $rows = [];
            foreach ($table->getRows() as $row) {
                $rows[] = $row->getData();
            }

            $columns = $table->getColumnNames();
        }

        foreach ($rows as $k => $row) {
            $rows[$k] = $this->remapLoadRow($model, $this->filterRowDataOnlyModelFields($model, $row));
        }

        $columns = array_keys($this->remapLoadRow($model, $this->filterRowDataOnlyModelFields($model, array_flip($columns))));

        if ($fields !== null) {
            $rows = array_map(static function (array $row) use ($fields) {
                return array_intersect_key($row, array_flip($fields));
            }, $rows);

            $columns = array_values(array_intersect($columns, $fields));
            assert(count($columns) === count($fields));
        }

        return new Action($rows, $columns);
    }

    /**
     * Will set limit defined inside $model onto Action.
     */
    protected function setLimitOrder(Model $model, Action $action): void
    {
        // first order by
        if (count($model->order) > 0) {
            $action->order($model->order);
        }

        // then set limit
        if ($model->limit[0] !== null || $model->limit[1] !== 0) {
            $action->limit($model->limit[0] ?? \PHP_INT_MAX, $model->limit[1]);
        }
    }

    /**
     * Will apply conditions defined inside $model onto Action.
     */
    protected function applyScope(Model $model, Action $action): void
    {
        $scope = $model->getModel(true)->scope();

        // add entity ID to scope to allow easy traversal
        if ($model->isEntity() && $model->idField && $model->getId() !== null) {
            $scope = new Model\Scope([$scope]);
            $scope->addCondition($model->getIdField(), $model->getId());
        }

        $action->filter($scope);
    }

    /**
     * Various actions possible here, mostly for compatibility with SQLs.
     *
     * @param array<mixed> $args
     *
     * @return Action
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
                    \Closure::bind(static function () use ($action, $args) {
                        $action->columns = [$args['alias']];
                    }, null, Action::class)();
                }

                return $action;
            case 'fx':
            case 'fx0':
                if (!isset($args[0]) || !isset($args[1])) {
                    throw (new Exception('fx action needs 2 arguments, eg: [\'sum\', \'amount\']'))
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
