<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

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

                $nativeConnection->sqliteCreateFunction('concat', static function ($value, ...$values): string {
                    $res = CreateRegexpLikeFunctionMiddleware::castScalarToString($value) ?? '';
                    foreach ($values as $v) {
                        $res .= CreateRegexpLikeFunctionMiddleware::castScalarToString($v);
                    }

                    return $res;
                }, -1, \PDO::SQLITE_DETERMINISTIC);

                return $connection;
            }
        };
    }
}
