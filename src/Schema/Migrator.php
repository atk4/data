<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Model\Join;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Reference;
use Atk4\Data\Reference\HasMany;
use Atk4\Data\Reference\HasOne;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Exception\TableNotFoundException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;

class Migrator
{
    public const REF_TYPE_NONE = 0;
    public const REF_TYPE_LINK = 1;
    public const REF_TYPE_PRIMARY = 2;

    /** @var Connection */
    private $_connection;

    /** @var Table */
    public $table;

    /** @var array<int, string> */
    private $createdTableNames = [];

    /**
     * @param Connection|Persistence\Sql|Model $source
     */
    public function __construct(object $source)
    {
        if ($source instanceof Connection) {
            $this->_connection = $source;
        } elseif ($source instanceof Persistence\Sql) {
            $this->_connection = $source->getConnection();
        } elseif ($source instanceof Model && $source->getPersistence() instanceof Persistence\Sql) { // @phpstan-ignore-line
            $this->_connection = $source->getPersistence()->getConnection();
        } else {
            throw (new Exception('Source must be SQL connection, persistence or initialized model'))
                ->addMoreInfo('source', $source);
        }

        if ($source instanceof Model) {
            $this->setModel($source);
        }
    }

    public function getConnection(): Connection
    {
        return $this->_connection;
    }

    protected function getDatabasePlatform(): AbstractPlatform
    {
        return $this->getConnection()->getDatabasePlatform();
    }

    /**
     * @phpstan-return AbstractSchemaManager<AbstractPlatform>
     */
    protected function createSchemaManager(): AbstractSchemaManager
    {
        return $this->getConnection()->createSchemaManager();
    }

    /**
     * Fix namespaced table name split for MSSQL/PostgreSQL.
     *
     * DBAL PR rejected: https://github.com/doctrine/dbal/pull/5494
     *
     * @template T of AbstractAsset
     *
     * @param T $abstractAsset
     *
     * @return T
     */
    protected function fixAbstractAssetName(AbstractAsset $abstractAsset, string $name): AbstractAsset
    {
        \Closure::bind(static function () use ($abstractAsset, $name) {
            $abstractAsset->_quoted = true;
            $lastDotPos = strrpos($name, '.');
            if ($lastDotPos !== false) {
                $abstractAsset->_namespace = substr($name, 0, $lastDotPos);
                $abstractAsset->_name = substr($name, $lastDotPos + 1);
            } else {
                $abstractAsset->_namespace = null;
                $abstractAsset->_name = $name;
            }
        }, null, AbstractAsset::class)();

        return $abstractAsset;
    }

    public function table(string $tableName): self
    {
        $table = $this->fixAbstractAssetName(new Table('0.0'), $tableName);
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $table->addOption('charset', 'utf8mb4');
        }

        $this->table = $table;

        return $this;
    }

    /**
     * @return array<int, string>
     */
    public function getCreatedTableNames(): array
    {
        return $this->createdTableNames;
    }

    public function create(): self
    {
        $this->createSchemaManager()->createTable($this->table);
        $this->createdTableNames[] = $this->table->getName();

        return $this;
    }

    public function drop(bool $dropForeignKeysFirst = false): self
    {
        $schemaManager = $this->createSchemaManager();

        if ($dropForeignKeysFirst) {
            // TODO https://github.com/doctrine/dbal/issues/5488 implement all foreign keys fetch in one query
            $foreignKeysByTableToDrop = [];
            foreach ($schemaManager->listTableNames() as $tableName) {
                $foreignKeys = $schemaManager->listTableForeignKeys($tableName);
                foreach ($foreignKeys as $foreignKey) {
                    if ($foreignKey->getForeignTableName() === $this->stripDatabaseFromTableName($this->table->getName())) {
                        $foreignKeysByTableToDrop[$tableName][] = $foreignKey;
                    }
                }
            }
            foreach ($foreignKeysByTableToDrop as $tableName => $foreignKeys) {
                foreach ($foreignKeys as $foreignKey) {
                    $schemaManager->dropForeignKey($foreignKey, $this->getDatabasePlatform()->quoteIdentifier($tableName));
                }
            }
        }

        $schemaManager->dropTable($this->table->getQuotedName($this->getDatabasePlatform()));

        $this->createdTableNames = array_diff($this->createdTableNames, [$this->table->getName()]);

        return $this;
    }

    public function dropIfExists(bool $dropForeignKeysFirst = false): self
    {
        try {
            $this->drop($dropForeignKeysFirst);
        } catch (TableNotFoundException $e) {
        }

        $this->createdTableNames = array_diff($this->createdTableNames, [$this->table->getName()]);

        // OracleSchemaManager::dropTable() called in self::drop() above tries to drop AI,
        // but if AI trigger is not present, AI sequence is not dropped
        // https://github.com/doctrine/dbal/issues/4997
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $schemaManager = $this->createSchemaManager();
            $dropTriggerSql = $this->getDatabasePlatform()
                ->getDropAutoincrementSql($this->table->getQuotedName($this->getDatabasePlatform()))[1];
            try {
                \Closure::bind(static function () use ($schemaManager, $dropTriggerSql) {
                    $schemaManager->_execSql($dropTriggerSql);
                }, null, AbstractSchemaManager::class)();
            } catch (DatabaseObjectNotFoundException $e) {
            }
        }

        return $this;
    }

    protected function stripDatabaseFromTableName(string $tableName): string
    {
        $platform = $this->getDatabasePlatform();
        $lastDotPos = strrpos($tableName, '.');
        if ($lastDotPos !== false) {
            $database = substr($tableName, 0, $lastDotPos);
            if ($platform instanceof PostgreSQLPlatform || $platform instanceof SQLServerPlatform) {
                $currentDatabase = $this->getConnection()->dsql()
                    ->field($this->getConnection()->expr('{{}}', [$this->getDatabasePlatform()->getCurrentDatabaseExpression(true)])) // @phpstan-ignore-line
                    ->getOne();
            } else {
                $currentDatabase = $this->getConnection()->getConnection()->getDatabase();
            }
            if ($database !== $currentDatabase) {
                throw (new Exception('Table name has database specified, but it does not match the current database'))
                    ->addMoreInfo('table', $tableName)
                    ->addMoreInfo('currentDatabase', $currentDatabase);
            }
            $tableName = substr($tableName, $lastDotPos + 1);
        }

        return $tableName;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function field(string $fieldName, array $options = []): self
    {
        $type = $options['type'] ?? 'string';
        unset($options['type']);
        if ($type === 'time' && $this->getDatabasePlatform() instanceof OraclePlatform) {
            $type = 'string';
        }

        $refType = $options['ref_type'] ?? self::REF_TYPE_NONE;
        unset($options['ref_type']);

        $column = $this->table->addColumn($this->getDatabasePlatform()->quoteSingleIdentifier($fieldName), $type);

        if (($options['nullable'] ?? true) && $refType !== self::REF_TYPE_PRIMARY) {
            $column->setNotnull(false);
        }

        if ($type === 'integer' && $refType !== self::REF_TYPE_NONE) {
            $column->setUnsigned(true);
        }

        // TODO remove, hack for createForeignKey so ID columns are unsigned
        if ($type === 'integer' && str_ends_with($fieldName, '_id')) {
            $column->setUnsigned(true);
        }

        if (in_array($type, ['string', 'text'], true)) {
            if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
                $column->setPlatformOption('collation', 'NOCASE');
            }
        }

        if ($refType === self::REF_TYPE_PRIMARY) {
            $this->table->setPrimaryKey([$this->getDatabasePlatform()->quoteSingleIdentifier($fieldName)]);
            $column->setAutoincrement(true);
        }

        return $this;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function id(string $name = 'id', array $options = []): self
    {
        $options = array_merge([
            'type' => 'integer',
            'ref_type' => self::REF_TYPE_PRIMARY,
            'nullable' => false,
        ], $options);

        $this->field($name, $options);

        return $this;
    }

    public function setModel(Model $model): Model
    {
        $this->table($model->table);

        foreach ($model->getFields() as $field) {
            if ($field->neverPersist || $field instanceof SqlExpressionField) {
                continue;
            }

            if ($field->shortName === $model->idField) {
                $refype = self::REF_TYPE_PRIMARY;
                $persistField = $field;
            } else {
                $refField = $field->hasReference() ? $this->getReferenceField($field) : null;
                if ($refField !== null) {
                    $refype = self::REF_TYPE_LINK;
                    $persistField = $refField;
                } else {
                    $refype = self::REF_TYPE_NONE;
                    $persistField = $field;
                }
            }

            $options = [
                'type' => $persistField->type,
                'ref_type' => $refype,
                'nullable' => ($field->nullable && !$field->required) || ($persistField->nullable && !$persistField->required),
            ];

            $this->field($field->getPersistenceName(), $options);
        }

        return $model;
    }

    protected function getReferenceField(Field $field): ?Field
    {
        $reference = $field->getReference();
        if ($reference instanceof HasOne) {
            $referenceField = $reference->getTheirFieldName($reference->createTheirModel());

            $modelSeed = is_array($reference->model)
                ? $reference->model
                : clone $reference->model;
            $referenceModel = Model::fromSeed($modelSeed, [new Persistence\Sql($this->getConnection())]);

            return $referenceModel->getField($referenceField);
        }

        return null;
    }

    protected function resolvePersistenceField(Field $field): ?Field
    {
        if ($field->neverPersist || $field instanceof SqlExpressionField) {
            return null;
        }

        if ($field->hasJoin()) {
            return $this->resolvePersistenceField(
                $field->getJoin()->getForeignModel()->getField($field->getPersistenceName())
            );
        }

        if (is_object($field->getOwner()->table)) {
            return $this->resolvePersistenceField(
                $field->getOwner()->table->getField($field->getPersistenceName())
            );
        }

        return $field;
    }

    /**
     * @param Reference|Join $relation
     *
     * @return array{Field, Field}
     */
    protected function resolveRelationDirection(object $relation): array
    {
        if ($relation instanceof HasOne) {
            $localField = $relation->getOwner()->getField($relation->getOurFieldName());
            $theirModel = $relation->createTheirModel();
            $foreignField = $theirModel->getField($relation->getTheirFieldName($theirModel));
        } elseif ($relation instanceof HasMany) {
            $localField = $relation->createTheirModel()->getField($relation->getTheirFieldName());
            $foreignField = $relation->getOwner()->getField($relation->getOurFieldName());
        } elseif ($relation instanceof Join) {
            $localField = $relation->getMasterField();
            $foreignField = $relation->getForeignModel()->getField($relation->foreignField);
            if ($relation->reverse) {
                [$localField, $foreignField] = [$foreignField, $localField];
            }
        } else {
            throw (new Exception('Relation must be HasOne, HasMany or Join'))
                ->addMoreInfo('relation', $relation);
        }

        return [$localField, $foreignField];
    }

    public function isTableExists(string $tableName): bool
    {
        try {
            [$sql] = $this->getConnection()->dsql()
                ->field($this->getConnection()->expr('1'))
                ->table($tableName)
                ->render();
            $this->getConnection()->getConnection()->executeQuery($sql);

            return true;
        } catch (TableNotFoundException $e) {
            return false;
        }
    }

    /**
     * DBAL list methods have very broken support for quoted table name
     * and almost no support for table name with database name.
     */
    protected function fixTableNameForListMethod(string $tableName): string
    {
        $tableName = $this->stripDatabaseFromTableName($tableName);

        $platform = $this->getDatabasePlatform();
        if ($platform instanceof MySQLPlatform || $platform instanceof SQLServerPlatform) {
            return $tableName;
        }

        return $platform->quoteSingleIdentifier($tableName);
    }

    /**
     * @param list<Field> $fields
     */
    public function isIndexExists(array $fields, bool $requireUnique): bool
    {
        $fields = array_map(fn ($field) => $this->resolvePersistenceField($field), $fields);
        $table = reset($fields)->getOwner()->table;

        $indexes = $this->createSchemaManager()->listTableIndexes($this->fixTableNameForListMethod($table));
        $fieldPersistenceNames = array_map(static fn ($field) => $field->getPersistenceName(), $fields);
        foreach ($indexes as $index) {
            $indexPersistenceNames = $index->getUnquotedColumns();
            if ($requireUnique) {
                if ($indexPersistenceNames === $fieldPersistenceNames && $index->isUnique()) {
                    return true;
                }
            } else {
                if (array_slice($indexPersistenceNames, 0, count($fieldPersistenceNames)) === $fieldPersistenceNames) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param list<Field> $fields
     */
    public function createIndex(array $fields, bool $isUnique): void
    {
        $fields = array_map(fn ($field) => $this->resolvePersistenceField($field), $fields);
        $table = reset($fields)->getOwner()->table;

        $platform = $this->getDatabasePlatform();

        $mssqlNullable = null;
        if ($platform instanceof SQLServerPlatform) {
            $mssqlNullable = false;
            foreach ($fields as $field) {
                if ($field->nullable && !$field->required) {
                    $mssqlNullable = true;
                }
            }
        }

        $index = new Index(
            \Closure::bind(static function () use ($table, $fields) {
                return (new Identifier(''))->_generateIdentifierName([
                    $table,
                    ...array_map(static fn ($field) => $field->getPersistenceName(), $fields),
                ], 'uniq');
            }, null, Identifier::class)(),
            array_map(static fn ($field) => $platform->quoteSingleIdentifier($field->getPersistenceName()), $fields),
            $isUnique,
            false,
            $mssqlNullable === false ? ['atk4-not-null'] : []
        );

        $this->createSchemaManager()->createIndex($index, $platform->quoteIdentifier($table));
    }

    /**
     * @param Reference|Join|array{Field, Field} $relation
     */
    public function createForeignKey($relation): void
    {
        [$localField, $foreignField] = is_array($relation)
            ? $relation
            : $this->resolveRelationDirection($relation);
        $localField = $this->resolvePersistenceField($localField);
        $foreignField = $this->resolvePersistenceField($foreignField);

        $platform = $this->getDatabasePlatform();

        if (!$this->isIndexExists([$foreignField], true)) {
            if ($foreignField->nullable && !$foreignField->required && $platform instanceof SQLServerPlatform) {
                $foreignFieldForIndex = clone $foreignField;
                $foreignFieldForIndex->nullable = false;
            } else {
                $foreignFieldForIndex = $foreignField;
            }

            $this->createIndex([$foreignFieldForIndex], true);
        }

        $foreignKey = new ForeignKeyConstraint(
            [$platform->quoteSingleIdentifier($localField->getPersistenceName())],
            '0.0',
            [$platform->quoteSingleIdentifier($foreignField->getPersistenceName())],
            // DBAL auto FK generator does not honor foreign table/columns
            // https://github.com/doctrine/dbal/pull/5490
            \Closure::bind(static function () use ($localField, $foreignField) {
                return (new Identifier(''))->_generateIdentifierName([
                    $localField->getOwner()->table,
                    $localField->getPersistenceName(),
                    $foreignField->getOwner()->table,
                    $foreignField->getPersistenceName(),
                ], 'fk');
            }, null, Identifier::class)()
        );
        $foreignTableIdentifier = $this->fixAbstractAssetName(new Identifier('0.0'), $foreignField->getOwner()->table);
        \Closure::bind(static fn () => $foreignKey->_foreignTableName = $foreignTableIdentifier, null, ForeignKeyConstraint::class)();

        $this->createSchemaManager()->createForeignKey($foreignKey, $platform->quoteIdentifier($localField->getOwner()->table));
    }
}
