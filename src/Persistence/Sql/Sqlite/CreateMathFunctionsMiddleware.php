<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Pdo\Sqlite;

/**
 * Remove once SQLite v3.34 support is dropped.
 */
class CreateMathFunctionsMiddleware implements Middleware
{
    private static ?bool $needed = null;

    public static function isNeeded(): bool
    {
        if (self::$needed === null) {
            try {
                $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
                $res = $connection->executeQuery('select power(0.7, 2.5), sqrt(null)')->fetchNumeric();
                assert((float) $res[0] === (0.7 ** 2.5));
                self::$needed = $res[1] !== null;
            } catch (DriverException $e) {
                assert(str_contains($e->getMessage(), 'no such function'));
                self::$needed = true;
            }
        }

        return self::$needed;
    }

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

                foreach ([
                    'power' => static fn (float $v, float $exp) => $v ** $exp,
                    'sqrt' => static fn (float $v) => sqrt($v),
                ] as $name => $fxInner) {
                    $numParams = (new \ReflectionFunction($fxInner))->getNumberOfParameters();

                    $fx = static function (...$args) use ($fxInner): ?float {
                        if (array_filter($args, static fn ($v) => $v === null) !== []) {
                            return null;
                        }

                        $args = array_map(static fn ($v) => (float) CreateRegexpLikeFunctionMiddleware::castScalarToString($v), $args);

                        return $fxInner(...$args);
                    };

                    if (\PHP_VERSION_ID < 8_04_00) {
                        $nativeConnection->sqliteCreateFunction($name, $fx, $numParams, \PDO::SQLITE_DETERMINISTIC);
                    } else {
                        assert($nativeConnection instanceof Sqlite);
                        $nativeConnection->createFunction($name, $fx, $numParams, Sqlite::DETERMINISTIC);
                    }
                }

                return $connection;
            }
        };
    }
}
