<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Field\SqlExpressionField;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Reference\HasOne;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
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
    public function __construct($source)
    {
        if (func_num_args() > 1) {
            throw new \Error();
        }

        if ($source instanceof Connection) {
            $this->_connection = $source;
        } elseif ($source instanceof Persistence\Sql) {
            $this->_connection = $source->getConnection();
        } elseif ($source instanceof Model && $source->getPersistence() instanceof Persistence\Sql) {
            $this->_connection = $source->getPersistence()->getConnection();
        } else {
            throw (new Exception('Source is specified incorrectly. Must be SQL connection, persistence or initialized model'))
                ->addMoreInfo('source', $source);
        }

        if ($source instanceof Model && $source->getPersistence() instanceof Persistence\Sql) {
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
        return $this->getConnection()->getConnection()->createSchemaManager();
    }

    public function table(string $tableName): self
    {
        $tableName = preg_replace('~^.+\.~', '', $tableName);

        $this->table = new Table($this->getDatabasePlatform()->quoteIdentifier($tableName));
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->table->addOption('charset', 'utf8mb4');
        }

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

    public function drop(): self
    {
        $this->createSchemaManager()
            ->dropTable($this->getDatabasePlatform()->quoteIdentifier($this->table->getName()));

        $this->createdTableNames = array_diff($this->createdTableNames, [$this->table->getName()]);

        return $this;
    }

    public function dropIfExists(): self
    {
        try {
            $this->drop();
        } catch (DbalException $e) {
            // TODO only non existing table exceptions should be ignored,
            // for now, do not ignore at least failed table drop due to
            // at least one linked foreign keys
            // should be also covered by tests more, also test self::create()
            // called twice to assert self::dropIfExists() is not called first
            if (str_contains(strtolower($e->getMessage()), 'foreign key')) {
                throw $e;
            }
        }

        $this->createdTableNames = array_diff($this->createdTableNames, [$this->table->getName()]);

        // OracleSchemaManager::dropTable() called in self::drop() above tries to drop AI,
        // but if AI trigger is not present, AI sequence is not dropped
        // https://github.com/doctrine/dbal/issues/4997
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $dropTriggerSql = $this->getDatabasePlatform()->getDropAutoincrementSql($this->table->getName())[1];
            try {
                $this->getConnection()->expr($dropTriggerSql)->executeStatement();
            } catch (Exception $e) {
            }
        }

        return $this;
    }

    public function field(string $fieldName, array $options = []): self
    {
        if (($options['type'] ?? null) === 'time' && $this->getDatabasePlatform() instanceof OraclePlatform) {
            $options['type'] = 'string';
        }

        $refType = $options['ref_type'] ?? self::REF_TYPE_NONE;
        unset($options['ref_type']);

        $column = $this->table->addColumn($this->getDatabasePlatform()->quoteSingleIdentifier($fieldName), $options['type'] ?? 'string');

        if (!($options['mandatory'] ?? false) && $refType !== self::REF_TYPE_PRIMARY) {
            $column->setNotnull(false);
        }

        if ($column->getType()->getName() === 'integer' && $refType !== self::REF_TYPE_NONE) {
            $column->setUnsigned(true);
        }

        if (in_array($column->getType()->getName(), ['string', 'text'], true)) {
            if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
                $column->setPlatformOption('collation', 'NOCASE');
            }
        }

        if ($refType === self::REF_TYPE_PRIMARY) {
            $this->table->setPrimaryKey([$this->getDatabasePlatform()->quoteSingleIdentifier($fieldName)]);
            $column->setAutoincrement(true);
        }

        return $this;
    }

    public function id(string $name = 'id'): self
    {
        $options = [
            'type' => 'integer',
            'ref_type' => self::REF_TYPE_PRIMARY,
            'mandatory' => true,
        ];

        $this->field($name, $options);

        return $this;
    }

    public function setModel(Model $model): Model
    {
        $this->table($model->table);

        foreach ($model->getFields() as $field) {
            if ($field->never_persist || $field instanceof SqlExpressionField) {
                continue;
            }

            if ($field->shortName === $model->id_field) {
                $refype = self::REF_TYPE_PRIMARY;
                $persistField = $field;
            } else {
                $refField = $this->getReferenceField($field);
                $refype = $refField !== null ? self::REF_TYPE_LINK : $refype = self::REF_TYPE_NONE;
                $persistField = $refField ?? $field;
            }

            $options = [
                'type' => $refype !== self::REF_TYPE_NONE && empty($persistField->type) ? 'integer' : $persistField->type,
                'ref_type' => $refype,
                'mandatory' => ($field->mandatory || $field->required) && ($persistField->mandatory || $persistField->required),
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
}
