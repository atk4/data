<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnectionWithState;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * @requires extension ssh2
 */
#[RequiresPhpExtension('ssh2')]
class MysqlConnectionWithStateTest extends TestCase
{
    protected function createConnection(): MysqlConnectionWithState
    {
        return new MysqlConnectionWithState(...MysqlConnectionTest::getSshConfig(), ...MysqlConnectionTest::getMysqlConfig());
    }

    public function testLastQuery(): void
    {
        $conn = $this->createConnection();

        self::assertNull($conn->lastQuery);

        $conn->sendQuery('start transaction');
        self::assertSame('start transaction', $conn->lastQuery);
        $conn->readResult();
        self::assertSame('start transaction', $conn->lastQuery);

        $conn->sendQuery('select 10');
        self::assertSame('select 10', $conn->lastQuery);
        $conn->readResult();
        self::assertSame('select 10', $conn->lastQuery);
    }

    public function testInQuerySinceTs(): void
    {
        $conn = $this->createConnection();

        self::assertNull($conn->inQuerySinceTs);

        $conn->sendQuery('start transaction');
        self::assertLessThan(0.5, abs(microtime(true) - $conn->inQuerySinceTs));
        $conn->readResult();
        self::assertNull($conn->inQuerySinceTs);
    }

    public function testIsolationLevel(): void
    {
        $conn = $this->createConnection();

        self::assertNull($conn->isolationLevel);

        $queryIsolationLevelFx = static function () use ($conn) {
            $queryIsolationLevelRaw = \Closure::bind(static fn () => $conn->queryIsolationLevelRaw(), null, MysqlConnectionWithState::class)();

            return [
                'SERIALIZABLE' => MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE,
                'REPEATABLE-READ' => MysqlConnectionWithState::ISOLATION_LEVEL_REPEATABLE_READ,
                'READ-COMMITTED' => MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED,
                'READ-UNCOMMITTED' => MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED,
            ][$queryIsolationLevelRaw[1]];
        };
        $assertIsolationLevelFx = static function (string $expectedIsolationLevel) use ($conn, $queryIsolationLevelFx) {
            self::assertSame($expectedIsolationLevel, $conn->isolationLevel);
            self::assertSame($expectedIsolationLevel, $queryIsolationLevelFx());
        };

        $conn->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE);

        $conn->sendQuery(' SET SESSION  TRANSACTION  ISOLATION  LEVEL  REPEATABLE  READ ');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_REPEATABLE_READ);

        $conn->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED);

        $conn->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ UNCOMMITTED');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED);

        $conn->sendQuery('start transaction');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED);

        $conn->sendQuery('commit');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED);

        $conn->sendQuery('start transaction');
        $conn->readResult();
        $assertIsolationLevelFx(MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED);
    }

    public function testInTransaction(): void
    {
        $conn = $this->createConnection();

        self::assertFalse($conn->inTransaction);

        $conn->sendQuery('start transaction');
        self::assertFalse($conn->inTransaction);
        $conn->readResult();
        self::assertTrue($conn->inTransaction);

        $conn->sendQuery(' commit ');
        self::assertTrue($conn->inTransaction);
        $conn->readResult();
        self::assertFalse($conn->inTransaction);

        $conn->sendQuery('start transaction');
        self::assertFalse($conn->inTransaction);
        $conn->readResult();
        self::assertTrue($conn->inTransaction);

        $conn->sendQuery('rollback');
        self::assertTrue($conn->inTransaction);
        $conn->readResult();
        self::assertFalse($conn->inTransaction);
    }

    public function testStartTransactionInTransactionException(): void
    {
        $conn = $this->createConnection();

        $conn->sendQuery('start transaction');
        $res = $conn->readResult();
        self::assertNull($res->error);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot start transaction when in transaction already');
        $conn->sendQuery('start transaction');
    }

    public function testUnsupportedQueryErrorMustThrowException(): void
    {
        $conn = $this->createConnection();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Query error: ERROR 1146 (');
        $conn->sendQuery('select 1 from atk4_test_wrong_table');
        $conn->readResult();
    }

    public function testQueryInTransaction(): void
    {
        $conn = $this->createConnection();

        $conn->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
        $conn->readResult();
        self::assertSame(MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED, $conn->isolationLevel);

        $queryInTransactionFx = \Closure::bind(static fn () => $conn->queryInTransaction(), null, MysqlConnectionWithState::class);

        self::assertFalse($queryInTransactionFx());
        self::assertSame(MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED, $conn->isolationLevel);

        $conn->sendQuery('start transaction');
        $conn->readResult();
        self::assertTrue($queryInTransactionFx());
        self::assertSame(MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED, $conn->isolationLevel);

        $conn->sendQuery('commit');
        $conn->readResult();
        self::assertFalse($queryInTransactionFx());
        self::assertSame(MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED, $conn->isolationLevel);

        $conn->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL SERIALIZABLE');
        $conn->readResult();
        self::assertSame(MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE, $conn->isolationLevel);
        $conn->sendQuery('start transaction');
        $conn->readResult();
        self::assertTrue($queryInTransactionFx());
        self::assertSame(MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE, $conn->isolationLevel);
    }
}
