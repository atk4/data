<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;
use Doctrine\DBAL\DriverManager;

class Connection extends BaseConnection
{
    private static string $driverVersion;

    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    #[\Override]
    protected static function createDbalConfiguration(): Configuration
    {
        $configuration = parent::createDbalConfiguration();

        $configuration->setMiddlewares([
            ...$configuration->getMiddlewares(),
            new EnableForeignKeys(),
            new PreserveAutoincrementOnRollbackMiddleware(),
        ]);

        return $configuration;
    }

    /**
     * @internal
     */
    public static function getDriverVersion(): string
    {
        if ((self::$driverVersion ?? null) === null) {
            $dbalConnection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
            $dbalConnection->connect();
            self::$driverVersion = $dbalConnection->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
        }

        return self::$driverVersion;
    }
}
