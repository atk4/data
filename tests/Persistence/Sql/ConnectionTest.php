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

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Connection
 */
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
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        $dsn = Connection::normalizeDsn('mysql:host=localhost;dbname=db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => null, 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        $dsn = Connection::normalizeDsn('mysql:host=localhost;dbname=db', 'root', 'pass');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        // username and password should take precedence
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost/db', 'foo', 'bar');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'foo', 'pass' => 'bar', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        // more options
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost/db;foo=bar');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db;foo=bar', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db;foo=bar'], $dsn);

        // no password
        $dsn = Connection::normalizeDsn('mysql://root@localhost/db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);
        $dsn = Connection::normalizeDsn('mysql://root:@localhost/db'); // see : after root
        $this->assertSame(['dsn' => 'mysql:host=localhost;dbname=db', 'user' => 'root', 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost;dbname=db'], $dsn);

        $dsn = Connection::normalizeDsn('sqlite::memory');
        $this->assertSame(['dsn' => 'sqlite::memory', 'user' => null, 'pass' => null, 'driverSchema' => 'sqlite', 'rest' => ':memory'], $dsn); // rest is unusable anyway in this context

        // with port number as URL, normalize port to ;port=1234
        $dsn = Connection::normalizeDsn('mysql://root:pass@localhost:1234/db');
        $this->assertSame(['dsn' => 'mysql:host=localhost;port=1234;dbname=db', 'user' => 'root', 'pass' => 'pass', 'driverSchema' => 'mysql', 'rest' => 'host=localhost;port=1234;dbname=db'], $dsn);

        // with port number as DSN, leave port as :port
        $dsn = Connection::normalizeDsn('mysql:host=localhost:1234;dbname=db');
        $this->assertSame(['dsn' => 'mysql:host=localhost:1234;dbname=db', 'user' => null, 'pass' => null, 'driverSchema' => 'mysql', 'rest' => 'host=localhost:1234;dbname=db'], $dsn);
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
        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);
        $c = new \Atk4\Data\Persistence\Sql\Sqlite\Connection('sqlite::memory'); // @phpstan-ignore-line
    }

    public function testException4(): void
    {
        $c = new \Atk4\Data\Persistence\Sql\Sqlite\Connection();
        $q = $c->expr('select (2+2)');

        $this->assertSame(
            'select (2+2)',
            $q->render()[0]
        );

        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);
        $q->execute();
    }
}
