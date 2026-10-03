<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

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

    #[\Override]
    public function lastInsertId(?string $sequence = null): string
    {
        if ($sequence) {
            return $this->dsql()->field($this->expr('{{}}.CURRVAL', [$sequence]))->getOne();
        }

        return parent::lastInsertId($sequence);
    }

    #[\Override]
    public function getServerVersion(bool $raw = false): string
    {
        $serverVersionRawRefl = new \ReflectionProperty(parent::class, 'serverVersionRaw');
        if (\PHP_VERSION_ID < 8_01_00) {
            $serverVersionRawRefl->setAccessible(true);
        }

        if (!$serverVersionRawRefl->isInitialized($this)) {
            // https://github.com/php/pecl-database-pdo_oci/issues/43
            $rowRaw = $this->getConnection()->executeQuery('SELECT version, version_full FROM sys.product_component_version WHERE product LIKE \'Oracle %Database%\'')->fetchNumeric();
            assert($rowRaw !== false);
            assert($rowRaw[0] === parent::getServerVersion(true));

            $serverVersionRawRefl->setValue($this, $rowRaw[1]);
        }

        return parent::getServerVersion($raw);
    }
}
