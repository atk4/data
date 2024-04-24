<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

class CreateRegexpReplaceFunctionMiddleware implements Middleware
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

                $nativeConnection->sqliteCreateFunction('regexp_replace', static function ($value, string $pattern, string $replacement, string $flags = ''): ?string {
                    if ($value === null) {
                        return null;
                    }

                    $value = CreateRegexpLikeFunctionMiddleware::castScalarToString($value);

                    $binary = !mb_check_encoding($pattern, 'UTF-8')
                        || !mb_check_encoding($value, 'UTF-8')
                        || !mb_check_encoding($replacement, 'UTF-8');

                    $pregPattern = '~' . preg_replace('~(?<!\\\)(?:\\\\\\\)*+\K\~~', '\\\~', $pattern) . '~'
                        . $flags . ($binary ? '' : 'u');

                    return preg_replace($pregPattern, $replacement, $value);
                }, -1, \PDO::SQLITE_DETERMINISTIC);

                return $connection;
            }
        };
    }
}
