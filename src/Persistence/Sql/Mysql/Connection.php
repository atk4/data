<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Platforms\MySQLPlatform;

class Connection extends BaseConnection
{
    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    public static function _getServerVersion(BaseConnection $connection): string
    {
        assert($connection->getDatabasePlatform() instanceof MySQLPlatform);

        // active server connection is required, but nothing is sent to the server
        return $connection->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore method.deprecated, method.notFound
    }

    public static function isServerMariaDb(BaseConnection $connection): bool
    {
        return preg_match('~(?<!\w)MariaDB(?!\w)~i', self::_getServerVersion($connection)) === 1;
    }

    /**
     * @return int<500, 5000>
     */
    public static function getServerMinorVersion(BaseConnection $connection): int
    {
        preg_match('~(\d+)\.(\d+)\.~', self::_getServerVersion($connection), $matches);

        return (int) $matches[1] * 100 + (int) $matches[2];
    }
}
