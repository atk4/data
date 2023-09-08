<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Persistence;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\MySQLPlatform;

/**
 * SQL persistence with lazy connect and SQL logger.
 *
 * @internal
 */
final class TestSqlPersistence extends Persistence\Sql
{
    public function __construct() // @phpstan-ignore-line
    {}

    public function getConnection(): Persistence\Sql\Connection
    {
        \Closure::bind(function () {
            if ($this->_connection === null) {
                $this->_connection = Persistence::connect($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'])->_connection; // @phpstan-ignore-line

                if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
                    $this->getConnection()->expr(
                        'SET SESSION auto_increment_increment = 1, SESSION auto_increment_offset = 1'
                    )->executeStatement();
                }

                // @phpstan-ignore-next-line SQLLogger is deprecated
                $this->getConnection()->getConnection()->getConfiguration()->setSQLLogger(
                    // @phpstan-ignore-next-line SQLLogger is deprecated
                    new class() implements SQLLogger {
                        public function startQuery($sql, array $params = null, array $types = null): void
                        {
                            // fix https://github.com/doctrine/dbal/issues/5525
                            if ($params !== null && $params !== [] && array_is_list($params)) {
                                $params = array_combine(range(1, count($params)), $params);
                            }

                            $test = TestCase::getTestFromBacktrace();
                            \Closure::bind(static fn () => $test->logQuery($sql, $params ?? [], $types ?? []), null, TestCase::class)(); // @phpstan-ignore-line
                        }

                        public function stopQuery(): void {}
                    }
                );
            }
        }, $this, Persistence\Sql::class)();

        return parent::getConnection();
    }
}
