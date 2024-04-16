<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

class CreateRegexpLikeFunctionMiddleware implements Middleware
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

                $nativeConnection->sqliteCreateFunction('regexp_like', static function ($value, string $pattern, string $flags = ''): bool {
                    if ($value === null) {
                        return false;
                    }

                    if (is_int($value)) {
                        $value = (string) $value;
                    } elseif (is_float($value)) {
                        $value = Expression::castFloatToString($value);
                    }

                    return preg_match('~' . str_replace('~', '\~', $pattern) . '~' . $flags, $value) === 1;
                }, -1, \PDO::SQLITE_DETERMINISTIC);

                return $connection;
            }
        };
    }
}
