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

                $nativeConnection->sqliteCreateFunction('regexp_like', static function ($value, string $pattern, string $flags = ''): ?bool {
                    if ($value === null) {
                        return null;
                    }

                    $value = CreateRegexpLikeFunctionMiddleware::castScalarToString($value);

                    $binary = \PHP_VERSION_ID < 80200
                        ? preg_match('~~u', $pattern) !== 1 // much faster in PHP 8.1 and lower
                            || preg_match('~~u', $value) !== 1
                        : !mb_check_encoding($pattern, 'UTF-8')
                            || !mb_check_encoding($value, 'UTF-8');

                    $pregPattern = '~' . preg_replace('~(?<!\\\)(?:\\\\\\\)*+\K\~~', '\\\~', $pattern) . '~'
                        . $flags . ($binary ? '' : 'u');

                    return preg_match($pregPattern, $value) === 1;
                }, -1, \PDO::SQLITE_DETERMINISTIC);

                return $connection;
            }
        };
    }

    /**
     * @param string|int|float|null $value
     */
    final public static function castScalarToString($value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            return Expression::castFloatToString($value);
        }

        return $value;
    }
}
