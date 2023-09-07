<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Exception as SqlException;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Reference\HasOneSql;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\Connection as DbalDriverConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class Sql extends Persistence
{
    use Sql\BinaryTypeCompatibilityTypecastTrait;

    public const HOOK_INIT_SELECT_QUERY = self::class . '@initSelectQuery';
    public const HOOK_BEFORE_INSERT_QUERY = self::class . '@beforeInsertQuery';
    public const HOOK_AFTER_INSERT_QUERY = self::class . '@afterInsertQuery';
    public const HOOK_BEFORE_UPDATE_QUERY = self::class . '@beforeUpdateQuery';
    public const HOOK_AFTER_UPDATE_QUERY = self::class . '@afterUpdateQuery';
    public const HOOK_BEFORE_DELETE_QUERY = self::class . '@beforeDeleteQuery';
    public const HOOK_AFTER_DELETE_QUERY = self::class . '@afterDeleteQuery';

    /** @var Connection */
    private $_connection;

    /** @var array<mixed> Default class when adding new field. */
    protected $_defaultSeedAddField; // no custom seed needed

    /** @var array<mixed> Default class when adding Expression field. */
    protected $_defaultSeedAddExpression = [SqlExpressionField::class];

    /** @var array<mixed> Default class when adding hasOne field. */
    protected $_defaultSeedHasOne = [HasOneSql::class];

    /** @var array<mixed> Default class when adding hasMany field. */
    protected $_defaultSeedHasMany; // no custom seed needed

    /** @var array<mixed> Default class when adding join. */
    protected $_defaultSeedJoin = [Sql\Join::class];

    /**
     * @param Connection|string|array<string, string>|DbalConnection|DbalDriverConnection $connection
     * @param string                                                                      $user
     * @param string                                                                      $password
     * @param array<string, mixed>                                                        $defaults
     */
    public function __construct($connection, $user = null, $password = null, $defaults = [])
    {
        if ($connection instanceof Connection) {
            $this->_connection = $connection;

            return;
        }

        // attempt to connect
        $this->_connection = Connection::connect(
            $connection,
            $user,
            $password,
            $defaults
        );
    }

    public function getConnection(): Connection
    {
        return $this->_connection;
    }

    /**
     * Disconnect from database explicitly.
     */
    public function disconnect(): void
    {
        parent::disconnect();

        $this->_connection = null; // @phpstan-ignore-line
    }

    public function atomic(\Closure $fx)
    {
        return $this->getConnection()->atomic($fx);
    }

    public function getDatabasePlatform(): AbstractPlatform
    {
        return $this->getConnection()->getDatabasePlatform();
    }

    public function add(Model $model, array $defaults = []): void
    {
        $defaults = array_merge([
            '_defaultSeedAddField' => $this->_defaultSeedAddField,
            '_defaultSeedAddExpression' => $this->_defaultSeedAddExpression,
            '_defaultSeedHasOne' => $this->_defaultSeedHasOne,
            '_defaultSeedHasMany' => $this->_defaultSeedHasMany,
            '_defaultSeedJoin' => $this->_defaultSeedJoin,
        ], $defaults);

        parent::add($model, $defaults);

        if ($model->table === null) {
            throw (new Exception('Property $table must be specified for a model'))
                ->addMoreInfo('model', $model);
        }

        // When we work without table, we can't have any IDs
        if ($model->table === false) {
            $model->removeField($model->idField);
            $model->addExpression($model->idField, ['expr' => '-1', 'type' => 'integer']);
        }
    }

    protected function initPersistence(Model $model): void
    {
        $model->addMethod('expr', static function (Model $m, ...$args) {
            return $m->getPersistence()->expr($m, ...$args);
        });
        $model->addMethod('dsql', static function (Model $m, ...$args) {
            return $m->getPersistence()->dsql($m, ...$args); // @phpstan-ignore-line
        });
        $model->addMethod('exprNow', static function (Model $m, ...$args) {
            return $m->getPersistence()->exprNow($m, ...$args);
        });
    }

    /**
     * Creates new Expression object from expression string.
     *
     * @param array<int|string, mixed> $arguments
     */
    public function expr(Model $model, string $template, array $arguments = []): Expression
    {
        preg_replace_callback(
            '~\[\w*\]|\{\w*\}~',
            static function ($matches) use ($model, &$arguments) {
                $identifier = substr($matches[0], 1, -1);
                if ($identifier !== '' && !isset($arguments[$identifier])) {
                    $arguments[$identifier] = $model->getField($identifier);
                }

                return $matches[0];
            },
            $template
        );

        return $this->getConnection()->expr($template, $arguments);
    }

    /**
     * Creates new Query object with current time expression.
     */
    public function exprNow(int $precision = null): Expression
    {
        return $this->getConnection()->dsql()->exprNow($precision);
    }

    /**
     * Creates new Query object.
     */
    public function dsql(): Query
    {
        return $this->getConnection()->dsql();
    }

    /**
     * Initializes base query for model $m.
     */
    public function initQuery(Model $model): Query
    {
        $query = $this->dsql();

        if ($model->table) {
            $query->table(
                is_object($model->table) ? $model->table->action('select') : $model->table,
                $model->tableAlias ?? (is_object($model->table) ? '_tm' : null)
            );
        }

        $this->initWithCursors($model, $query);

        return $query;
    }

    public function initWithCursors(Model $model, Query $query): void
    {
        foreach ($model->cteModels as $withAlias => ['model' => $withModel, 'recursive' => $withRecursive]) {
            $subQuery = $withModel->action('select');
            $query->with($subQuery, $withAlias, null, $withRecursive);
        }
    }

    /**
     * Adds Field in Query.
     */
    public function initField(Query $query, Field $field): void
    {
        $query->field($field, $field->useAlias() ? $field->shortName : null);
    }

    /**
     * Adds model fields in Query.
     *
     * @param array<int, string>|null $fields
     */
    public function initQueryFields(Model $model, Query $query, array $fields = null): void
    {
        // init fields
        if ($fields !== null) {
            // Set of fields is strictly defined for purposes of export,
            // so we will ignore even system fields.
            foreach ($fields as $fieldName) {
                $this->initField($query, $model->getField($fieldName));
            }
        } elseif ($model->onlyFields !== null) {
            $addedFields = [];

            // Add requested fields first
            foreach ($model->onlyFields as $fieldName) {
                $field = $model->getField($fieldName);
                if ($field->neverPersist) {
                    continue;
                }
                $this->initField($query, $field);
                $addedFields[$fieldName] = true;
            }

            // now add system fields, if they were not added
            foreach ($model->getFields() as $fieldName => $field) {
                if ($field->neverPersist) {
                    continue;
                }
                if ($field->system && !isset($addedFields[$fieldName])) {
                    $this->initField($query, $field);
                }
            }
        } else {
            foreach ($model->getFields() as $fieldName => $field) {
                if ($field->neverPersist) {
                    continue;
                }
                $this->initField($query, $field);
            }
        }
    }

    /**
     * Will set limit defined inside $m onto query $q.
     */
    protected function setLimitOrder(Model $model, Query $query): void
    {
        // set limit
        if ($model->limit[0] !== null || $model->limit[1] !== 0) {
            $query->limit($model->limit[0] ?? \PHP_INT_MAX, $model->limit[1]);
        }

        // set order
        foreach ($model->order as $order) {
            $isDesc = strtolower($order[1]) === 'desc';

            if ($order[0] instanceof Expressionable) {
                $query->order($order[0], $isDesc);
            } else {
                $query->order($model->getField($order[0]), $isDesc);
            }
        }
    }

    /**
     * Will apply model scope/conditions onto $query.
     */
    public function initQueryConditions(Model $model, Query $query): void
    {
        $this->_initQueryConditions($query, $model->getModel(true)->scope());

        // add entity ID to scope to allow easy traversal
        if ($model->isEntity() && $model->idField && $model->getId() !== null) {
            $query->group($model->getField($model->idField));
            $this->fixMssqlOracleMissingFieldsInGroup($model, $query);
            $query->having($model->getField($model->idField), $model->getId());
        }
    }

    private function fixMssqlOracleMissingFieldsInGroup(Model $model, Query $query): void
    {
        if ($this->getDatabasePlatform() instanceof SQLServerPlatform
                || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $isIdFieldInGroup = false;
            foreach ($query->args['group'] ?? [] as $v) {
                if ($model->idField && $v === $model->getField($model->idField)) {
                    $isIdFieldInGroup = true;

                    break;
                }
            }

            if ($isIdFieldInGroup) {
                foreach ($query->args['field'] ?? [] as $field) {
                    if ($field instanceof Field) {
                        $query->group($field);
                    }
                }
            }
        }
    }

    private function _initQueryConditions(Query $query, Model\Scope\AbstractScope $condition = null): void
    {
        if (!$condition->isEmpty()) {
            // peel off the single nested scopes to convert (((field = value))) to field = value
            $condition = $condition->simplify();

            // simple condition
            if ($condition instanceof Model\Scope\Condition) {
                $query->where(...$condition->toQueryArguments());
            }

            // nested conditions
            if ($condition instanceof Model\Scope) {
                $expression = $condition->isOr() ? $query->orExpr() : $query->andExpr();

                foreach ($condition->getNestedConditions() as $nestedCondition) {
                    $this->_initQueryConditions($expression, $nestedCondition);
                }

                $query->where($expression);
            }
        }
    }

    /**
     * @param array<mixed> $args
     *
     * @return Query
     */
    public function action(Model $model, string $type, array $args = [])
    {
        switch ($type) {
            case 'select':
                $query = $this->initQuery($model);
                $this->initQueryFields($model, $query, $args[0] ?? null);
                $this->initQueryConditions($model, $query);
                $this->setLimitOrder($model, $query);
                $model->hook(self::HOOK_INIT_SELECT_QUERY, [$query, $type]);

                return $query;
            case 'count':
                $query = $this->initQuery($model);
                $this->initQueryConditions($model, $query);
                $model->hook(self::HOOK_INIT_SELECT_QUERY, [$query, $type]);

                return $query->reset('field')->field('count(*)', $args['alias'] ?? null);
            case 'exists':
                $query = $this->initQuery($model);
                $this->initQueryConditions($model, $query);
                $model->hook(self::HOOK_INIT_SELECT_QUERY, [$query, $type]);

                return $query->exists();
            case 'field':
                if (!isset($args[0])) {
                    throw (new Exception('This action requires one argument with field name'))
                        ->addMoreInfo('action', $type);
                }
                $field = $args[0];
                if (is_string($field)) {
                    $field = $model->getField($field);
                }

                $query = $this->action($model, 'select', [[]]);

                if (isset($args['alias'])) {
                    $query->reset('field')->field($field, $args['alias']);
                } elseif ($field instanceof SqlExpressionField) {
                    $query->reset('field')->field($field, $field->shortName);
                } else {
                    $query->reset('field')->field($field);
                }
                $this->fixMssqlOracleMissingFieldsInGroup($model, $query);

                if ($model->isEntity() && $model->isLoaded()) {
                    $idRaw = $this->typecastSaveField($model->getField($model->idField), $model->getId());
                    $query->where($model->getField($model->idField), $idRaw);
                }

                return $query;
            case 'fx':
            case 'fx0':
                if (!isset($args[0]) || !isset($args[1])) {
                    throw (new Exception('fx action needs 2 arguments, eg: ["sum", "amount"]'))
                        ->addMoreInfo('action', $type);
                }
                [$fx, $field] = $args;
                if (is_string($field)) {
                    $field = $model->getField($field);
                }

                $query = $this->action($model, 'select', [[]]);

                if ($fx === 'concat') {
                    $expr = $query->groupConcat($field, $args['concatSeparator']);
                } else {
                    $expr = $query->expr(
                        $type === 'fx'
                            ? $fx . '([])'
                            : 'coalesce(' . $fx . '([]), 0)',
                        [$field]
                    );
                }

                if (isset($args['alias'])) {
                    $query->reset('field')->field($expr, $args['alias']);
                } elseif ($field instanceof SqlExpressionField) {
                    $query->reset('field')->field($expr, $fx . '_' . $field->shortName);
                } else {
                    $query->reset('field')->field($expr);
                }
                $this->fixMssqlOracleMissingFieldsInGroup($model, $query);

                return $query;
            default:
                throw (new Exception('Unsupported action mode'))
                    ->addMoreInfo('type', $type);
        }
    }

    public function tryLoad(Model $model, $id): ?array
    {
        $model->assertIsModel();

        $noId = $id === self::ID_LOAD_ONE || $id === self::ID_LOAD_ANY;

        $query = $model->action('select');

        if (!$noId) {
            if (!$model->idField) {
                throw (new Exception('Unable to load field by "id" when Model->idField is not defined'))
                    ->addMoreInfo('id', $id);
            }

            $idRaw = $this->typecastSaveField($model->getField($model->idField), $id);
            $query->where($model->getField($model->idField), $idRaw);
        }
        $query->limit(
            min($id === self::ID_LOAD_ANY ? 1 : 2, $query->args['limit']['cnt'] ?? \PHP_INT_MAX),
            $query->args['limit']['shift'] ?? null
        );

        // execute action
        try {
            $rowsRaw = $query->getRows();
            if (count($rowsRaw) === 0) {
                return null;
            } elseif (count($rowsRaw) !== 1) {
                throw (new Exception('Ambiguous conditions, more than one record can be loaded'))
                    ->addMoreInfo('model', $model)
                    ->addMoreInfo('idField', $model->idField)
                    ->addMoreInfo('id', $noId ? null : $id);
            }
            $data = $this->typecastLoadRow($model, $rowsRaw[0]);
        } catch (SqlException $e) {
            throw (new Exception('Unable to load due to query error', 0, $e))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        if ($model->idField && !isset($data[$model->idField])) {
            // TODO detect even an ID change here!
            throw (new Exception('Model uses "idField" but it was not available in the database'))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('idField', $model->idField)
                ->addMoreInfo('id', $noId ? null : $id)
                ->addMoreInfo('data', $data);
        }

        return $data;
    }

    /**
     * Export all DataSet.
     *
     * @param array<int, string>|null $fields
     *
     * @return array<int, array<string, mixed>>
     */
    public function export(Model $model, array $fields = null, bool $typecast = true): array
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
     * @return \Traversable<array<string, mixed>>
     */
    public function prepareIterator(Model $model): \Traversable
    {
        $export = $model->action('select');

        try {
            return $export->getRowsIterator();
        } catch (SqlException $e) {
            throw (new Exception('Unable to execute iteration query', 0, $e))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }
    }

    /**
     * @param mixed $idRaw
     */
    private function assertExactlyOneRecordUpdated(Model $model, $idRaw, int $affectedRows, string $operation): void
    {
        if ($affectedRows !== 1) {
            throw (new Exception(ucfirst($operation) . ' failed, exactly 1 row was expected to be affected'))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords())
                ->addMoreInfo('idRaw', $idRaw)
                ->addMoreInfo('affectedRows', $affectedRows);
        }
    }

    /**
     * @param array<scalar|Expressionable|null> $dataRaw
     */
    protected function insertRaw(Model $model, array $dataRaw)
    {
        $insert = $this->initQuery($model);
        $insert->mode('insert');

        $insert->setMulti($dataRaw);

        $model->hook(self::HOOK_BEFORE_INSERT_QUERY, [$insert]);

        try {
            $c = $insert->executeStatement();
        } catch (SqlException $e) {
            throw (new Exception('Unable to execute insert query', 0, $e))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        $this->assertExactlyOneRecordUpdated($model, null, $c, 'insert');

        if ($model->idField) {
            $idRaw = $dataRaw[$model->getField($model->idField)->getPersistenceName()] ?? null;
            if ($idRaw === null) {
                $idRaw = $this->lastInsertId($model);
            }
        } else {
            $idRaw = '';
        }

        $model->hook(self::HOOK_AFTER_INSERT_QUERY, [$insert]);

        return $idRaw;
    }

    /**
     * @param array<scalar|Expressionable|null> $dataRaw
     */
    protected function updateRaw(Model $model, $idRaw, array $dataRaw): void
    {
        $update = $this->initQuery($model);
        $update->mode('update');

        // only apply fields that has been modified
        $update->setMulti($dataRaw);
        $update->where($model->getField($model->idField)->getPersistenceName(), $idRaw);

        $model->hook(self::HOOK_BEFORE_UPDATE_QUERY, [$update]);

        try {
            $c = $update->executeStatement();
        } catch (SqlException $e) {
            throw (new Exception('Unable to update due to query error', 0, $e))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        $this->assertExactlyOneRecordUpdated($model, $idRaw, $c, 'update');

        $model->hook(self::HOOK_AFTER_UPDATE_QUERY, [$update]);
    }

    protected function deleteRaw(Model $model, $idRaw): void
    {
        $delete = $this->initQuery($model);
        $delete->mode('delete');
        $delete->where($model->getField($model->idField)->getPersistenceName(), $idRaw);
        $model->hook(self::HOOK_BEFORE_DELETE_QUERY, [$delete]);

        try {
            $c = $delete->executeStatement();
        } catch (SqlException $e) {
            throw (new Exception('Unable to delete due to query error', 0, $e))
                ->addMoreInfo('model', $model)
                ->addMoreInfo('scope', $model->scope()->toWords());
        }

        $this->assertExactlyOneRecordUpdated($model, $idRaw, $c, 'delete');

        $model->hook(self::HOOK_AFTER_DELETE_QUERY, [$delete]);
    }

    public function typecastSaveField(Field $field, $value)
    {
        $value = parent::typecastSaveField($field, $value);

        if ($value !== null && $this->binaryTypeIsEncodeNeeded($field->type)) {
            $value = $this->binaryTypeValueEncode($value);
        }

        return $value;
    }

    public function typecastLoadField(Field $field, $value)
    {
        $value = parent::typecastLoadField($field, $value);

        if ($value !== null && $this->binaryTypeIsDecodeNeeded($field->type, $value)) {
            $value = $this->binaryTypeValueDecode($value);
        }

        return $value;
    }

    public function getFieldSqlExpression(Field $field, Expression $expression): Expression
    {
        if (isset($field->getOwner()->persistenceData['use_table_prefixes'])) {
            $mask = '{{}}.{}';
            $prop = [
                $field->hasJoin()
                    ? ($field->getJoin()->foreignAlias ?? $field->getJoin()->shortName)
                    : ($field->getOwner()->tableAlias ?? (is_object($field->getOwner()->table) ? '_tm' : $field->getOwner()->table)),
                $field->getPersistenceName(),
            ];
        } else {
            // references set flag use_table_prefixes, so no need to check them here
            $mask = '{}';
            $prop = [
                $field->getPersistenceName(),
            ];
        }

        // If our Model has expr() method (inherited from Persistence\Sql) then use it
        if ($field->getOwner()->hasMethod('expr')) {
            return $field->getOwner()->expr($mask, $prop);
        }

        // Otherwise call method from expression
        return $expression->expr($mask, $prop);
    }

    public function lastInsertId(Model $model): string
    {
        if (is_object($model->table)) {
            throw new \Error('Table must be a string');
        }

        // PostgreSQL and Oracle DBAL platforms use sequence internally for PK autoincrement,
        // use default name if not set explicitly
        $sequenceName = null;
        if ($this->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $sequenceName = $this->getConnection()->getDatabasePlatform()->getIdentitySequenceName(
                $model->table,
                $model->getField($model->idField)->getPersistenceName()
            );
        } elseif ($this->getConnection()->getDatabasePlatform() instanceof OraclePlatform) {
            $sequenceName = $model->table . '_SEQ';
        }

        return $this->getConnection()->lastInsertId($sequenceName);
    }
}
