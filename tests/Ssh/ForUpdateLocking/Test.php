<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnection;
use Atk4\Data\Ssh\MysqlConnectionWithState;
use Atk4\Data\Tests\Ssh\MysqlConnectionTest;
use Atk4\Data\Tests\Ssh\MysqliAsyncConnectionTest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * @requires extension ssh2
 */
#[RequiresPhpExtension('ssh2')]
class Test extends TestCase
{
    private int $obLevel;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->obLevel = ob_get_level();

        for ($i = 0; $i < $this->obLevel; ++$i) {
            ob_end_flush();
        }

        \Closure::bind(static function () {
            MysqlConnection::$nextId = 0;
        }, null, MysqlConnection::class)();
    }

    #[\Override]
    protected function tearDown(): void
    {
        for ($i = 0; $i < $this->obLevel; ++$i) {
            ob_start();
        }

        parent::tearDown();
    }

    protected function createConnection(string $table): ConnectionWithValue
    {
        /** @var bool */
        $usingMysqli = true;

        $conn = new ConnectionWithValue(
            ...($usingMysqli ? MysqliAsyncConnectionTest::class : MysqlConnectionTest::class)::getSshConfig(),
            ...MysqlConnectionTest::getMysqlConfig(),
            ...[$table]
        );
        $conn->enableDebugPrint = true;

        return $conn;
    }

    protected function makeRandomTableName(): string
    {
        return 'tt_' . md5(microtime(true) . random_bytes(64));
    }

    protected function createTestTable(string $table): void
    {
        $conn = $this->createConnection($table);
        $conn->enableDebugPrint = false;

        $conn->sendQuery(<<<'EOD'
            CREATE TABLE $TTT (
              `name` varchar(50) CHARACTER SET ascii NOT NULL,
              `value` bigint UNSIGNED NOT NULL,
              PRIMARY KEY (`name`)
            ) ENGINE=InnoDB
            EOD);
        $conn->readResult();

        $conn->sendQuery('insert into $TTT values (\'a\', 100), (\'b\', 200)');
        $conn->readResult();
    }

    /**
     * Reported as https://jira.mariadb.org/browse/MDEV-36959 .
     */
    public function testIssueTransactionTemporaryTurnedOffAfterDeadlock(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $connA = $this->createConnection($table);
        $connA->enableDebugPrint = false;
        $connA->enableAssertInTransactionUsingQuery = true;
        $connB = $this->createConnection($table);
        $connB->enableDebugPrint = false;

        $connA->sendQuery('start transaction');
        $connA->readResult();

        $connB->sendQuery('start transaction');
        $connB->readResult();

        $connA->sendQuery('update $TTT set value = 703 where name = \'b\'');
        $connA->readResult();

        $connB->sendQuery('update $TTT set value = 795 where name != \'b\'');

        $connA->sendQuery('update $TTT set value = 944 where name != \'b\'');
        // ERROR 1213 (40001): Deadlock found when trying to get lock; try restarting transaction
        // here we read "not in transaction"
        $connA->readResult();
        $connB->readResult();

        $connA->sendQuery('update $TTT set value = 646');

        $connB->sendQuery('commit');

        if ($connA->serverIsMariaDB || version_compare($connA->serverVersion, '5.7') < 0) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Wrong "inTransaction" assumed');
        } else {
            self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType
        }

        // here we read "in transaction"
        $connA->readResult();
    }

    /**
     * Not reported yet. Retest once testIssueTransactionTemporaryTurnedOffAfterDeadlock test is fixed by MariaDB.
     */
    public function testIssueTransactionTemporaryTurnedOffAfterLockInShareMode(): void
    {
        // fix test reliability (failure rate is about 1 % in Github Actions)
        for ($i = 0; $i < 4; ++$i) {
            $this->_testIssueTransactionTemporaryTurnedOffAfterLockInShareMode();
        }
    }

    private function _testIssueTransactionTemporaryTurnedOffAfterLockInShareMode(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $connA = $this->createConnection($table);
        $connA->enableDebugPrint = false;
        $connA->enableAssertInTransactionUsingQuery = true;
        $connB = $this->createConnection($table);
        $connB->enableDebugPrint = false;

        $connA->sendQuery('start transaction');
        $connA->readResult();

        $connB->sendQuery('start transaction');
        $connB->readResult();

        $connB->sendQuery('select * from $TTT where name = \'a\' lock in share mode');
        $connB->readResult();

        $connA->sendQuery('update $TTT set value = value + 25 where name = \'a\'');

        $connB->sendQuery('select * from $TTT where name = \'a\' for update');

        // ERROR 1213 (40001): Deadlock found when trying to get lock; try restarting transaction
        // or ERROR 1205 (HY000): Lock wait timeout exceeded; try restarting transaction
        // here we read "not in transaction" - see condition below since which MySQL/MariaDB version the issue is no longer present
        $connA->readResult();

        $connA->sendQuery('select * from $TTT where name = \'a\'');

        if (
            $connA->serverIsMariaDB
                ? version_compare($connA->serverVersion, '11.7') < 0
                    && $connA->serverVersion !== '10.5.15' // maybe also 10.5.14 which was unreleased by MariaDB
                    && !(version_compare($connA->serverVersion, '11.4.5') >= 0 && version_compare($connA->serverVersion, '11.5.0') < 0)
                : version_compare($connA->serverVersion, '5.7') < 0
        ) {
            $this->expectException(Exception::class);
            $this->expectExceptionMessage('Wrong "inTransaction" assumed');
        } else {
            self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType
        }

        // here we read "in transaction"
        $connA->readResult();
    }

    /**
     * @param MysqlConnectionWithState::ISOLATION_LEVEL_* $isolationLevel
     *
     * @dataProvider provideIsolationLevelCases
     */
    #[DataProvider('provideIsolationLevelCases')]
    public function testSelectForUpdateWithNonEqualsCondition(string $isolationLevel): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $connA = $this->createConnection($table);
        $connA->enableDebugPrint = false;
        $connB = $this->createConnection($table);
        $connB->enableDebugPrint = false;

        $connA->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolationLevel);
        $connA->readResult();

        $connB->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolationLevel);
        $connB->readResult();

        $connB->sendQuery('start transaction');
        $connB->readResult();

        $connB->sendQuery('update $TTT set value = 10');
        $connB->readResult();

        $connA->sendQuery('start transaction');
        $connA->readResult();

        $connA->sendQuery('select * from $TTT where name = \'b\'');
        $res = $connA->readResult();
        self::assertSame(
            $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE
                ? []
                : [['name' => 'b', 'value' => $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED ? '10' : '200']],
            $res->rows
        );

        $connB->sendQuery('commit');
        $connB->readResult();

        for ($i = 0; $i < 2; ++$i) {
            $isRepeatableReadMariaDb116 = $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_REPEATABLE_READ
                && $connA->serverIsMariaDB && version_compare($connA->serverVersion, '11.6') >= 0;

            $connA->sendQuery('select * from $TTT where name != \'b\' for update');
            $res = $connA->readResult();
            self::assertSame($isRepeatableReadMariaDb116 && $i === 0 ? [] : [['name' => 'a', 'value' => '10']], $res->rows);

            $connA->sendQuery('select * from $TTT where name != \'b\'');
            $res = $connA->readResult();
            self::assertSame([['name' => 'a', 'value' => $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_REPEATABLE_READ && !$isRepeatableReadMariaDb116 ? '100' : '10']], $res->rows);
        }
    }

    public function testLockedValueTracking(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $conn = $this->createConnection($table);
        $conn->enableDebugPrint = false;

        self::assertNull($conn->lockedValue);

        $conn->sendQuery('start transaction');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('update $TTT set value = 10 where name = \'a\'');
        $conn->readResult();
        self::assertSame(10, $conn->lockedValue);

        $conn->sendQuery('update $TTT set value = value + 10 where name = \'a\'');
        $conn->readResult();
        self::assertSame(20, $conn->lockedValue);

        $conn->sendQuery('update $TTT set value = value + 10 where name = \'b\'');
        $conn->readResult();
        self::assertSame(20, $conn->lockedValue);

        $conn->sendQuery('update $TTT set value = value + 10 where name != \'b\'');
        $conn->readResult();
        self::assertSame(30, $conn->lockedValue);

        $conn->sendQuery('update $TTT set value = value + 10');
        $conn->readResult();
        self::assertSame(40, $conn->lockedValue);

        $conn->sendQuery('commit');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('update $TTT set value = value + 10');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('start transaction');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('select * from $TTT');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('select * from $TTT for update');
        $conn->readResult();
        self::assertSame(50, $conn->lockedValue);

        $conn->sendQuery('select * from $TTT');
        $conn->readResult();
        self::assertSame(50, $conn->lockedValue);

        $conn->sendQuery('update $TTT set value = value + 10');
        $conn->readResult();
        self::assertSame(60, $conn->lockedValue);

        $conn->sendQuery('rollback');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('select * from $TTT for update');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('start transaction');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->sendQuery('select * from $TTT for update');
        $conn->readResult();
        self::assertSame(50, $conn->lockedValue);
    }

    public function testLockedValueTrackingParseException(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $conn = $this->createConnection($table);
        $conn->enableDebugPrint = false;

        $conn->sendQuery('start transaction');
        $conn->readResult();

        $conn->sendQuery('update $TTT set value = value - 10');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Failed to parse update query');
        $conn->readResult();
    }

    public function testLockedValueTrackingValueMismatchException(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $conn = $this->createConnection($table);
        $conn->enableDebugPrint = false;

        $conn->sendQuery('start transaction');
        $conn->readResult();
        self::assertNull($conn->lockedValue);

        $conn->lockedValue = 50;

        $conn->sendQuery('select * from $TTT for update');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Tracked locked value does not match actual value');
        $conn->readResult();
    }

    /**
     * @param MysqlConnectionWithState::ISOLATION_LEVEL_* $isolationLevel
     *
     * @dataProvider provideIsolationLevelCases
     */
    #[DataProvider('provideIsolationLevelCases')]
    public function testLockedValueUpdateImpliesSelectForUpdateNoMatterIsolationLevel(string $isolationLevel): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $conn = $this->createConnection($table);
        $conn->enableDebugPrint = false;
        $connOther = $this->createConnection($table);
        $connOther->enableDebugPrint = false;

        $conn->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolationLevel);
        $conn->readResult();

        $connOther->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL ' . $isolationLevel);
        $connOther->readResult();

        $conn->sendQuery('start transaction');
        $conn->readResult();

        $conn->sendQuery('update $TTT set value = 10 where name = \'a\'');
        $conn->readResult();
        self::assertSame(10, $conn->lockedValue);
        self::assertNull($connOther->lockedValue);

        $connOther->sendQuery('update $TTT set value = 20 where name = \'a\'');
        $res = $connOther->readResult();
        self::assertSame(10, $conn->lockedValue);
        self::assertNull($connOther->lockedValue);
        self::assertNotNull($res->error);
        self::assertStringContainsString('ERROR 1205 (HY000): Lock wait timeout exceeded', $res->error);
        if (version_compare($conn->serverVersion, $conn->serverIsMariaDB ? '10.6' : '8.0.28') < 0) { // https://jira.mariadb.org/browse/MDEV-36960
            self::assertGreaterThan(1 - 0.6, $res->elapsed);
            self::assertLessThan(1 + 2.5, $res->elapsed);
        } else {
            self::assertEqualsWithDelta(1.0, $res->elapsed, 0.6);
        }
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideIsolationLevelCases(): iterable
    {
        yield [MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE];
        yield [MysqlConnectionWithState::ISOLATION_LEVEL_REPEATABLE_READ];
        yield [MysqlConnectionWithState::ISOLATION_LEVEL_READ_COMMITTED];
        yield [MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED];
    }

    public function testRunForUpdateTester(): void
    {
        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType

        $maxTime = 15.0;
        $startTs = microtime(true);
        $lastDumpTs = $startTs;

        for ($i = 1;; ++$i) {
            $ts = microtime(true);
            if ($ts >= $startTs + $maxTime) {
                return;
            }

            \Closure::bind(static function () {
                MysqlConnection::$nextId = 0;
            }, null, MysqlConnection::class)();

            $table = $this->makeRandomTableName();
            $this->createTestTable($table);

            try {
                ob_start();

                $tester = new Tester(fn () => $this->createConnection($table), 3);
                $tester->run(5.0);

                ob_end_clean();
            } catch (\Throwable $e) {
                ob_end_flush();

                throw $e;
            }

            if ($ts > $lastDumpTs + 20) {
                $lastDumpTs = $ts;
                echo '==== ' . round($ts - $startTs, 2) . ' run ' . $i . ' ================' . "\n";
            }
        }
    }
}
