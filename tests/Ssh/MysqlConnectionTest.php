<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnection;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * @requires extension ssh2
 */
#[RequiresPhpExtension('ssh2')]
class MysqlConnectionTest extends TestCase
{
    /**
     * @return array{string, string}
     */
    public static function getSshConfig(): array
    {
        return [getenv('SSH_HOST'), getenv('SSH_USER')];
    }

    /**
     * @return array{string, int, string, string, string}
     */
    public static function getMysqlConfig(): array
    {
        // docker setup:
        // cd for-update
        // docker run -it --network=host -e MYSQL_ROOT_PASSWORD=r -e MYSQL_DATABASE=d -e MYSQL_TCP_PORT=4057 -v ${PWD}:/etc/mysql/conf.d mysql:5.7

        return [getenv('DB_HOST'), (int) getenv('DB_PORT'), getenv('DB_USER'), getenv('DB_PASSWORD'), getenv('DB_DATABASE')];
    }

    protected function createConnection(): MysqlConnection
    {
        return new MysqlConnection(...static::getSshConfig(), ...static::getMysqlConfig());
    }

    public function testSelectEmpty(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('select 1 from dual where 1=0');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);
    }

    public function testSelectSingleRow(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('select \'my foo\', 1 as `x y`');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([
            ['my foo' => 'my foo', 'x y' => '1'],
        ], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);
    }

    public function testSelectTwoRows(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('select x from (select 1 as x union all select 2 as x) t order by x');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([
            ['x' => '1'],
            ['x' => '2'],
        ], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);
    }

    public function testSelectUnnamedSlowColumn(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('select sleep(1)');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([
            ['sleep(1)' => '0'],
        ], $res->rows);
        self::assertGreaterThan(0.9, $res->elapsed);
        self::assertLessThan(1.1, $res->elapsed);
    }

    public function testTransactionManagement(): void
    {
        $conn = $this->createConnection();

        $conn->sendQuery('start transaction');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);

        $conn->sendQuery('rollback');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);
    }

    public function testInsertUpdateDelete(): void
    {
        $conn = $this->createConnection();

        $conn->sendQuery(<<<'EOD'
            CREATE TABLE atk4_test_conn (
                n varchar(50) CHARACTER SET ascii NOT NULL,
                PRIMARY KEY (n)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            EOD);
        $res = $conn->readResult();
        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);

        $conn->sendQuery('insert into atk4_test_conn values (\'foo\')');
        $res = $conn->readResult();
        self::assertNull($res->error);
        self::assertSame(1, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);

        $conn->sendQuery('update atk4_test_conn set n = \'bar\'');
        $res = $conn->readResult();
        self::assertNull($res->error);
        self::assertSame(1, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);

        $conn->sendQuery('delete from atk4_test_conn where n = \'bar\'');
        $res = $conn->readResult();
        self::assertNull($res->error);
        self::assertSame(1, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);

        $conn->sendQuery('drop table atk4_test_conn');
        $res = $conn->readResult();
        self::assertNull($res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);
    }

    public function testInvalidQuery(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('xxx');
        $res = $conn->readResult();

        self::assertStringContainsString('ERROR 1064 (42000): You have an error in your SQL syntax;', $res->error);
        self::assertSame(0, $res->affectedRows);
        self::assertSame([], $res->rows);
        self::assertLessThan(0.1, $res->elapsed);
    }

    public function testHasMoreData(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('select 1');
        // do not test $conn->hasMoreData() == false here, race condition possible
        usleep(100_000);
        self::assertTrue($conn->hasMoreData());
        $conn->readResult();
        self::assertFalse($conn->hasMoreData());
        usleep(100_000);
        self::assertFalse($conn->hasMoreData());
    }

    public function testCiMysqldCnfHonored(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('show variables like \'max_connections\'');
        $res = $conn->readResult();

        self::assertNull($res->error);
        self::assertSame([
            ['Variable_name' => 'max_connections', 'Value' => '100'],
        ], $res->rows);
    }

    public function testCiMaxRootConnectionsHonored(): void
    {
        $conns = [];
        for ($i = 0; $i < 11; ++$i) {
            if ($i === 10) {
                $this->expectException(Exception::class);
                $this->expectExceptionMessage('Too many connections');
            }

            $conn = $this->createConnection();
            $conns[] = $conn;
        }
    }
}
