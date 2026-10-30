<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Pdo\Sqlite;

class CreateMathFunctionsMiddleware implements Middleware
{
    private static ?bool $nativeMathSupport = null;
    private static ?bool $nativePercentileSupport = null;

    public static function nativeMathSupport(): bool
    {
        if (self::$nativeMathSupport === null) {
            if (Sql\Connection::isDbal3x()) { // https://github.com/doctrine/dbal/commit/7959ea48546244ce8184771123da0e4d85f96f26
                self::$nativeMathSupport = false;
            } else {
                try {
                    $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
                    $res = $connection->executeQuery('select power(0.7, 2.5), sqrt(null)')->fetchNumeric();
                    assert((float) $res[0] === (0.7 ** 2.5));
                    assert($res[1] === null);
                    self::$nativeMathSupport = true;
                } catch (DriverException $e) {
                    assert(str_contains($e->getMessage(), 'no such function'));
                    self::$nativeMathSupport = false;
                }
            }
        }

        return self::$nativeMathSupport;
    }

    public static function nativePercentileSupport(): bool
    {
        if (self::$nativePercentileSupport === null) {
            try {
                $connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
                $res = $connection->executeQuery('select percentile_cont(v, 0.2) from (select 1 v union select 3)')->fetchNumeric();
                assert((float) $res[0] === 1.4);
                self::$nativePercentileSupport = true;
            } catch (DriverException $e) {
                assert(str_contains($e->getMessage(), 'no such function'));
                self::$nativePercentileSupport = false;
            }
        }

        return self::$nativePercentileSupport;
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
                    'floor' => static fn (float $v) => floor($v),
                    'ceil' => static fn (float $v) => ceil($v),
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
