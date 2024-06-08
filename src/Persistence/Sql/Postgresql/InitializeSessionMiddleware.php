<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Setup "citext" server extension to be available as we use "citext" type for all bound string variables.
 *
 * Based on https://github.com/doctrine/dbal/blob/3.6.5/src/Driver/OCI8/Middleware/InitializeSession.php
 */
class InitializeSessionMiddleware implements Middleware
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

                if ($connection->query('SELECT to_regtype(\'citext\')')->fetchOne() === null) {
                    // "CREATE EXTENSION IF NOT EXISTS ..." cannot be used as it requires
                    // CREATE privilege even if the extension is already installed
                    $connection->query('CREATE EXTENSION citext');
                }

                return $connection;
            }
        };
    }
}
