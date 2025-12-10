<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\Connection as DbalDriverConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\PDO\SQLSrv\Connection as DbalDriverPdoMssqlConnection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/**
 * SQL persistence with lazy connect and SQL logger.
 *
 * @internal
 */
class TestSqlPersistence extends Persistence\Sql
{
    public function __construct() {} // @phpstan-ignore constructor.missingParentCall

    #[\Override]
    public function getConnection(): Connection
    {
        \Closure::bind(function () {
            if (($this->_connection ?? null) === null) {
                $this->_connection = Persistence::connect($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD'])->_connection; // @phpstan-ignore property.notFound

                if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                    $this->getConnection()->expr(
                        'SET SESSION auto_increment_increment = 1, SESSION auto_increment_offset = 1'
                    )->executeStatement();
                }

                $this->wrapDeepestDbalDriverConnection( // @phpstan-ignore method.notFound
                    $this->_connection->getConnection(),
                    TestLogConnectionMiddleware::class
                );
            }
        }, $this, Persistence\Sql::class)();

        return parent::getConnection();
    }

    /**
     * @param DbalConnection|DbalDriverConnection        $connection
     * @param class-string<AbstractConnectionMiddleware> $middlewareClass
     *
     * @return ($connection is DbalConnection ? null : AbstractConnectionMiddleware)
     */
    protected function wrapDeepestDbalDriverConnection($connection, string $middlewareClass): ?AbstractConnectionMiddleware
    {
        if ($connection instanceof DbalConnection) {
            $reflProp = new \ReflectionProperty(DbalConnection::class, '_conn');
            if (\PHP_VERSION_ID < 8_01_00) {
                $reflProp->setAccessible(true);
            }

            $newMiddleware = $this->wrapDeepestDbalDriverConnection(
                $reflProp->getValue($connection),
                $middlewareClass
            );

            $reflProp->setValue($connection, $newMiddleware);

            return null;
        }

        if ($connection instanceof AbstractConnectionMiddleware && !$connection instanceof DbalDriverPdoMssqlConnection) {
            $reflProp = new \ReflectionProperty(AbstractConnectionMiddleware::class, 'wrappedConnection');
            if (\PHP_VERSION_ID < 8_01_00) {
                $reflProp->setAccessible(true);
            }

            $newMiddleware = $this->wrapDeepestDbalDriverConnection(
                $reflProp->getValue($connection),
                $middlewareClass
            );

            return new $connection($newMiddleware);
        }

        return new $middlewareClass($connection);
    }
}
