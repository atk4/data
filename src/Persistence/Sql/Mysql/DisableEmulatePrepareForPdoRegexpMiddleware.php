<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

class DisableEmulatePrepareForPdoRegexpMiddleware implements Middleware
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
                return new DisableEmulatePrepareForPdoRegexpConnectionMiddleware(parent::connect($params));
            }
        };
    }
}
