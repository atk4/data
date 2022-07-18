<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Query;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * SQL persistence with lazy connect and SQL logger.
 *
 * @internal
 */
final class TestSqlPersistence extends Persistence\Sql
{
    public function __construct()
    {
    }

    public function getConnection(): Persistence\Sql\Connection
    {
        \Closure::bind(function () {
            if ($this->_connection === null) {
                $connection = Persistence::connect($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'])->_connection; // @phpstan-ignore-line
                $this->_connection = $connection;

                if ($connection->getDatabasePlatform() instanceof MySQLPlatform) {
                    $connection->expr(
                        'SET SESSION auto_increment_increment = 1, SESSION auto_increment_offset = 1'
                    )->executeStatement();
                }

                $connection->getConnection()->getConfiguration()->setSQLLogger(
                    // @phpstan-ignore-next-line SQLLogger is deprecated
                    null ?? new class() implements SQLLogger {
                        public function startQuery($sql, array $params = null, array $types = null): void
                        {
                            $test = TestCase::getTestFromBacktrace();
                            \Closure::bind(fn () => $test->logQuery($sql, $params ?? [], $types ?? []), null, TestCase::class)();
                        }

                        public function stopQuery(): void
                        {
                        }
                    }
                );
            }
        }, $this, Persistence\Sql::class)();

        return parent::getConnection();
    }

    public function initQuery(Model $model): Query
    {
        $this->setupforeignKeysFromModel($model);

        return parent::initQuery($model);
    }

    /** @var array<\Closure> */
    public $afterTransactionCallbacks = [];

    public function atomic(\Closure $fx)
    {
        $res = parent::atomic($fx);

        if (!$this->getConnection()->inTransaction()) {
            while (count($this->afterTransactionCallbacks) > 0) {
                $fx = array_shift($this->afterTransactionCallbacks); // can be slow with large array
                $fx();
            }
        }

        return $res;
    }

    /** @var array<string> */
    private $processingTables = [];

    public function setupforeignKeysFromModel(Model $model): void
    {
        $table = $model;
        do {
            $table = $table->table;
        } while (is_object($table));
        if (isset($this->processingTables[$table])) {
            return;
        }

        $this->processingTables[$table] = true;
        try {
            $migrator = new Migrator($this);
            $migrator->debugSetupForeignKeysFromModel($model);
        } finally {
            unset($this->processingTables[$table]);
        }
    }
}
