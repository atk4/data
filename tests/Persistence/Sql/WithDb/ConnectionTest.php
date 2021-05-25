<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Platforms\OraclePlatform;

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Query
 */
class ConnectionTest extends AtkPhpunit\TestCase
{
    public function testServerConnection(): void
    {
        $c = Connection::connect($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        $this->assertSame('1', $c->expr('SELECT 1' . ($c->getDatabasePlatform() instanceof OraclePlatform ? ' FROM DUAL' : ''))->getOne());
    }
}
