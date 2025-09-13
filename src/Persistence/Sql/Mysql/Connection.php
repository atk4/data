<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Platforms\MySQLPlatform;

class Connection extends BaseConnection
{
    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    public static function isServerMariaDb(BaseConnection $connection): bool
    {
        assert($connection->getDatabasePlatform() instanceof MySQLPlatform);

        return preg_match('~(?<!\w)MariaDB(?!\w)~i', $connection->getServerVersion(true)) === 1;
    }
}
