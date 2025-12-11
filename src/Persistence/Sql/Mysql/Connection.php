<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

class Connection extends BaseConnection
{
    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    public static function isServerMariaDb(BaseConnection $connection): bool
    {
        assert($connection->getDatabasePlatform() instanceof AbstractMySQLPlatform);

        return preg_match('~(?<!\w)MariaDB(?!\w)~i', $connection->getServerVersion(true)) === 1;
    }

    #[\Override]
    public function getServerVersion(bool $raw = false): string
    {
        // https://github.com/php/php-src/issues/7972
        if (\PHP_VERSION_ID < 8_01_03 && parent::getServerVersion() === '5.5.5') {
            \Closure::bind(function () {
                $this->serverVersionRaw = preg_replace('~^5\.5\.5-(?=\d+\.\d+\.\d+.*-MariaDB-)~', '', $this->serverVersionRaw);
            }, $this, parent::class)();
        }

        return parent::getServerVersion($raw);
    }
}
