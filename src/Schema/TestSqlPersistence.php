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
                            if (!$test->debug) {
                                return;
                            }

                            echo "\n" . $sql . (substr($sql, -1) !== ';' ? ';' : '') . "\n"
                                . (is_array($params) && count($params) > 0 ? substr(print_r(array_map(function ($v) {
                                    if ($v === null) {
                                        $v = 'null';
                                    } elseif (is_bool($v)) {
                                        $v = $v ? 'true' : 'false';
                                    } elseif (is_float($v) && (string) $v === (string) (int) $v) {
                                        $v = $v . '.0';
                                    } elseif (is_string($v)) {
                                        if (strlen($v) > 4096) {
                                            $v = '*long string* (length: ' . strlen($v) . ' bytes, sha256: ' . hash('sha256', $v) . ')';
                                        } else {
                                            $v = '\'' . $v . '\'';
                                        }
                                    }

                                    return $v;
                                }, $params), true), 6) : '') . "\n";
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
}
