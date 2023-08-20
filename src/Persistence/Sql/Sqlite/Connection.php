<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver\AbstractSQLiteDriver\Middleware\EnableForeignKeys;

class Connection extends BaseConnection
{
    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    protected static function createDbalConfiguration(): Configuration
    {
        $configuration = parent::createDbalConfiguration();

        $configuration->setMiddlewares([...$configuration->getMiddlewares(), new EnableForeignKeys()]);

        return $configuration;
    }
}
