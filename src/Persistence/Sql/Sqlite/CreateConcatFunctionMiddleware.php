<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Pdo\Sqlite;

/**
 * Remove once SQLite v3.43 support is dropped.
 */
class CreateConcatFunctionMiddleware implements Middleware
{
    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            #[\Override]
            public function connect(
                #[\SensitiveParameter]
                array $params
            ): Connection {
                $connection = parent::connect($params);

                $nativeConnection = $connection->getNativeConnection();
                assert($nativeConnection instanceof \PDO);

                $fx = static function ($value, ...$values): string {
                    $res = CreateRegexpLikeFunctionMiddleware::castScalarToString($value) ?? '';
                    foreach ($values as $v) {
                        $res .= CreateRegexpLikeFunctionMiddleware::castScalarToString($v);
                    }

                    return $res;
                };
                if (\PHP_VERSION_ID < 8_04_00) {
                    $nativeConnection->sqliteCreateFunction('concat', $fx, -1, \PDO::SQLITE_DETERMINISTIC);
                } else {
                    assert($nativeConnection instanceof Sqlite);
                    $nativeConnection->createFunction('concat', $fx, -1, Sqlite::DETERMINISTIC);
                }

                return $connection;
            }
        };
    }
}
