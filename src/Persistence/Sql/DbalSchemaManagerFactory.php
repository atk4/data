<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\OracleSchemaManager;
use Doctrine\DBAL\Schema\SchemaManagerFactory;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;

class DbalSchemaManagerFactory implements SchemaManagerFactory
{
    /**
     * @phpstan-return AbstractSchemaManager<AbstractPlatform>
     */
    #[\Override]
    public function createSchemaManager(DbalConnection $connection): AbstractSchemaManager
    {
        $platform = $connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            return new class($connection, $platform) extends SQLiteSchemaManager {
                use Sqlite\SchemaManagerTrait;
            };
        } elseif ($platform instanceof OraclePlatform) {
            return new class($connection, $platform) extends OracleSchemaManager {
                use Oracle\SchemaManagerTrait;
            };
        }

        return $connection->getDatabasePlatform()->createSchemaManager($connection);
    }
}
