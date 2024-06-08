<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Configuration;

class Connection extends BaseConnection
{
    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    #[\Override]
    protected static function createDbalConfiguration(): Configuration
    {
        $configuration = parent::createDbalConfiguration();

        $configuration->setMiddlewares([
            ...$configuration->getMiddlewares(),
            new InitializeSessionMiddleware(),
        ]);

        return $configuration;
    }
}
