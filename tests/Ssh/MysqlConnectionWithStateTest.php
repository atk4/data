<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnectionWithState;
use Atk4\Data\Ssh\MysqlException;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * @requires extension ssh2
 */
#[RequiresPhpExtension('ssh2')]
class MysqlConnectionWithStateTest extends TestCase
{
    protected function createConnection(): MysqlConnectionWithState
    {
        return new MysqlConnectionWithState(...MysqliAsyncConnectionTest::getSshConfig(), ...MysqlConnectionTest::getMysqlConfig());
    }

    public function testLastQuery(): void
    {
        $conn = $this->createConnection();
        $conn = $this->createConnection(); // make sure server version is always loaded from cache

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

    public function testInvalidQueryException(): void
    {
        $conn = $this->createConnection();
        $conn->sendQuery('xxx');

        $this->expectException(MysqlException::class);
        $this->expectExceptionCode(1064);
        $this->expectExceptionMessage('Query error: ERROR 1064 (42000): You have an error in your SQL syntax;');
        $conn->readResult();
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
        $conn->readResult();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Cannot start transaction when in transaction already');
        $conn->sendQuery('start transaction');
    }

    public function testUnsupportedQueryErrorMustThrowException(): void
    {
        $conn = $this->createConnection();

        $this->expectException(Exception::class);
        $this->expectExceptionCode(1146);
        $this->expectExceptionMessage('Query error: ERROR 1146 (');
        $conn->sendQuery('select 1 from atk4_test_wrong_table');
        $conn->readResult();
    }

    public function testExecuteAndRetryOnInterruptedQuery(): void
    {
        // fix test reliability (failure rate is about 10 ppm in Github Actions)
        for ($i = 0;; ++$i) {
            try {
                $this->_testExecuteAndRetryOnInterruptedQuery();

                return;
            } catch (AssertionFailedError $e) { // @phpstan-ignore catch.internalClass
                if ($i >= 4) {
                    throw $e;
                }
            }
        }
    }

    private function _testExecuteAndRetryOnInterruptedQuery(): void
    {
        $conn = $this->createConnection();
        $connOther = $this->createConnection();

        for ($j = 0; $j < 5_000; ++$j) { // loop to test MysqlConnectionWithState::waitUntilQueryIsExecuting() extensively
            $conn->sendQuery('select sleep(60)');
            $conn->waitUntilQueryIsExecuting($connOther);

            $connOther->sendQuery('kill query ' . $conn->threadId);
            $connOther->readResult();

            $i = 0;
            $conn->executeAndRetryOnInterruptedQuery(function () use ($conn, &$i, $j) {
                if (++$i > 1) {
                    return;
                }

                $e = null;
                try {
                    $conn->readResult();
                } catch (MysqlException $e) {
                    self::assertSame(1317, $e->getCode());
                }
                if ($conn->serverIsMariaDB ? version_compare($conn->serverVersion, '10.5.6') <= 0 : true) {
                    // MySQL and MariaDB 10.5.6 and lower do not emit an error when "select sleep(10)" is interrupted
                    self::assertNull($e);
                    $this->expectException(AssertionFailedError::class); // @phpstan-ignore classConstant.internalClass
                }
                self::assertNotNull($e, 'iter: ' . $j);

                throw $e;
            });
            self::assertSame(2, $i);

            $conn->sendQuery('select CONNECTION_ID()');
            $res = $conn->readResult();
            self::assertSame([['CONNECTION_ID()' => (string) $conn->threadId]], $res->rows);
        }

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Too many interrupted query retries');
        $i = 0;
        try {
            $conn->executeAndRetryOnInterruptedQuery(static function () use ($conn, $connOther, &$i) {
                ++$i;

                $conn->sendQuery('select sleep(60)');
                $conn->waitUntilQueryIsExecuting($connOther);

                $connOther->sendQuery('kill query ' . $conn->threadId);
                $connOther->readResult();

                $e = null;
                try {
                    $conn->readResult();
                } catch (MysqlException $e) {
                    self::assertSame(1317, $e->getCode());
                }
                self::assertNotNull($e);

                throw $e;
            });
        } finally {
            self::assertSame(50, $i);
        }
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
