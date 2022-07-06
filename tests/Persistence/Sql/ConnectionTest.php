<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class DummyConnection extends Connection
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new class() extends SqlitePlatform {
            public function getName()
            {
                return 'dummy';
            }
        };
    }
}

class DummyConnection2 extends Connection
{
    public function getDatabasePlatform(): AbstractPlatform
    {
        return new class() extends SqlitePlatform {
            public function getName()
            {
                return 'dummy2';
            }
        };
    }
}

class ConnectionTest extends TestCase
{
    public function testInit(): void
    {
        $c = Connection::connect('sqlite::memory:');
        $this->assertSame(
            '4',
            $c->expr('select (2+2)')->getOne()
        );
    }

    /**
     * Test DSN normalize.
     */
    public function testDsnNormalize(): void
    {
        // standard
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost/db');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('mysql:host=localhost;dbname=db');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('mysql:host=localhost;dbname=db', 'root', 'pass');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'dbname' => 'db', 'user' => 'root', 'password' => 'pass'], $dsn);

        // username and password should take precedence
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost/db', 'foo', 'bar');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'user' => 'foo', 'password' => 'bar', 'dbname' => 'db'], $dsn);

        // more options
        $dsn = Connection::normalizeDsn('mysql:host=localhost;dbname=db;foo=x;bar=y', 'root', 'pass');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'dbname' => 'db', 'foo' => 'x', 'bar' => 'y', 'user' => 'root', 'password' => 'pass'], $dsn);
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost/db;foo=x;bar=y');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db', 'foo' => 'x', 'bar' => 'y'], $dsn);

        // no password
        $dsn = Connection::normalizeDsn('mysql://root@localhost/db');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'user' => 'root', 'dbname' => 'db'], $dsn);
        $dsn = Connection::normalizeDsn('mysql://root:@localhost/db'); // see : after root
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'user' => 'root', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('sqlite::memory');
        $this->assertSame(['driver' => 'pdo_sqlite', 'memory' => true], $dsn); // rest is unusable anyway in this context

        // with port number as URL, normalize port to ;port=1234
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost:1234/db');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'port' => '1234', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        // with port number as DSN, leave port as :port
        $dsn = Connection::normalizeDsn('mysql:host=localhost:1234;dbname=db');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'dbname' => 'db', 'port' => '1234'], $dsn);

        // driverOptions array
        $dsn = Connection::normalizeDsn('pdo-sqlsrv://localhost:1234/db?driverOptions[TrustServerCertificate]=1');
        $this->assertSame(['driver' => 'pdo_sqlsrv', 'host' => 'localhost', 'port' => '1234', 'driverOptions' => ['TrustServerCertificate' => '1'], 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('pdo_sqlsrv:host=localhost:1234;dbname=db;driverOptions[TrustServerCertificate]=1');
        $this->assertSame(['driver' => 'pdo_sqlsrv', 'host' => 'localhost', 'dbname' => 'db', 'driverOptions' => ['TrustServerCertificate' => '1'], 'port' => '1234'], $dsn);

        // full PDO and native driver names
        $dsn = Connection::normalizeDsn('pdo-mysql://root:pass@localhost/db');
        $this->assertSame(['driver' => 'pdo_mysql', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('pdo_mysql:host=localhost;dbname=db', 'root', 'pass');
        $this->assertSame(['driver' => 'pdo_mysql', 'host' => 'localhost', 'dbname' => 'db', 'user' => 'root', 'password' => 'pass'], $dsn);

        $dsn = Connection::normalizeDsn('pdo-pgsql://root:pass@localhost/db');
        $this->assertSame(['driver' => 'pdo_pgsql', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('pdo-sqlsrv://root:pass@localhost/db');
        $this->assertSame(['driver' => 'pdo_sqlsrv', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('pdo-oci://root:pass@localhost/db');
        $this->assertSame(['driver' => 'pdo_oci', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('mysqli://root:pass@localhost/db');
        $this->assertSame(['driver' => 'mysqli', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('pgsql://root:pass@localhost/db');
        $this->assertSame(['driver' => 'pdo_pgsql', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('sqlsrv://root:pass@localhost/db');
        $this->assertSame(['driver' => 'pdo_sqlsrv', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('oci://root:pass@localhost/db');
        $this->assertSame(['driver' => 'oci8', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);

        $dsn = Connection::normalizeDsn('oci8://root:pass@localhost/db');
        $this->assertSame(['driver' => 'oci8', 'host' => 'localhost', 'user' => 'root', 'password' => 'pass', 'dbname' => 'db'], $dsn);
    }

    public function testConnectionRegistry(): void
    {
        $registryBackup = \Closure::bind(fn () => Connection::$connectionClassRegistry, null, Connection::class)();
        try {
            Connection::registerConnectionClass(DummyConnection::class, 'dummy');
            $this->assertSame(DummyConnection::class, Connection::resolveConnectionClass('dummy'));
            try {
                Connection::resolveConnectionClass('dummy2');
                $this->assertFalse(true);
            } catch (\Exception $e) {
            }

            Connection::registerConnectionClass(DummyConnection2::class, 'dummy2');
            $this->assertSame(DummyConnection2::class, Connection::resolveConnectionClass('dummy2'));

            $this->assertNotSame($registryBackup, \Closure::bind(fn () => Connection::$connectionClassRegistry, null, Connection::class)());
        } finally {
            \Closure::bind(fn () => Connection::$connectionClassRegistry = $registryBackup, null, Connection::class)();
        }
    }

    public function testMysqlFail(): void
    {
        $this->expectException(\Exception::class);
        $c = Connection::connect('mysql:host=256.256.256.256'); // invalid host
    }

    public function testException1(): void
    {
        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);
        $c = \Atk4\Data\Persistence\Sql\Sqlite\Connection::connect(':');
    }

    public function testException2(): void
    {
        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);
        $c = Connection::connect('');
    }

    public function testException3(): void
    {
        $this->expectException(\TypeError::class);
        $c = new \Atk4\Data\Persistence\Sql\Sqlite\Connection('sqlite::memory'); // @phpstan-ignore-line
    }

    public function testException4(): void
    {
        $c = new \Atk4\Data\Persistence\Sql\Sqlite\Connection();
        $q = $c->expr('select (2 + 2)');

        $this->assertSame(
            'select (2 + 2)',
            $q->render()[0]
        );

        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);
        $q->executeQuery();
    }
}
