<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnection;
use Atk4\Data\Ssh\MysqlConnectionWithState;
use Atk4\Data\Ssh\MysqlException;
use Atk4\Data\Tests\Ssh\MysqlConnectionTest;
use Atk4\Data\Tests\Ssh\MysqliAsyncConnectionTest;
use PHPUnit\Framework\Attributes\DataProvider;

class Test extends TestCase
{
    private int $obLevel;

    private function isMvorisekLocal(): bool
    {
        return strtoupper(substr(\PHP_OS, 0, 3)) === 'WIN';
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->obLevel = ob_get_level();

        for ($i = 0; $i < $this->obLevel; ++$i) {
            ob_end_flush();
        }

        if (!$this->isMvorisekLocal() && !extension_loaded('ssh2')) {
            self::markTestSkipped('SSH TEST');
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
            ...($this->isMvorisekLocal() ? ['10.8.128.25', 4050, 'root', 'r', 'd'] : MysqlConnectionTest::getMysqlConfig()),
            ...[$table]
        );

        return $conn;
    }

    protected function makeRandomTableName(): string
    {
        return 'tt_' . md5(microtime(true) . random_bytes(64));
    }

    protected function createTestTable(string $table): void
    {
        $conn = $this->createConnection($table);

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
        $connA->enableAssertInTransactionUsingQuery = true;
        $connB = $this->createConnection($table);

        $connA->sendQuery('start transaction');
        $connA->readResult();

        $connB->sendQuery('start transaction');
        $connB->readResult();

        $connA->sendQuery('update $TTT set value = 703 where name = \'b\'');
        $connA->readResult();

        $connB->sendQuery('update $TTT set value = 795 where name != \'b\'');
        usleep(100_000); // MysqlConnectionWithState::waitUntilQueryIsExecuting() is not working on MySQL and also it must detect already executed/finished query

        $connA->sendQuery('update $TTT set value = 944 where name != \'b\'');

        // here we read "not in transaction"
        $e = null;
        try {
            $connA->readResult();
        } catch (MysqlException $e) {
            // ERROR 1205 (HY000): Lock wait timeout exceeded; try restarting transaction
            // ERROR 1213 (40001): Deadlock found when trying to get lock; try restarting transaction
            self::assertContains($e->getCode(), [1205, 1213]);
        }
        self::assertNotNull($e);

        $connB->readResult();

        $connA->sendQuery('update $TTT set value = 646');

        $connB->sendQuery('commit');

        if (
            $connA->serverIsMariaDB
                ? (version_compare($connA->serverVersion, '10.11.13') <= 0
                    || (version_compare($connA->serverVersion, '11.0') >= 0 && version_compare($connA->serverVersion, '11.4.7') <= 0)
                    || (version_compare($connA->serverVersion, '11.5') >= 0 && version_compare($connA->serverVersion, '11.8.2') <= 0))
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
     * Seem related with testIssueTransactionTemporaryTurnedOffAfterDeadlock test as fixed by the same issue/release.
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
        $connA->enableAssertInTransactionUsingQuery = true;
        $connB = $this->createConnection($table);

        $connA->sendQuery('start transaction');
        $connA->readResult();

        $connB->sendQuery('start transaction');
        $connB->readResult();

        $connB->sendQuery('select * from $TTT where name = \'a\' lock in share mode');
        $connB->readResult();

        $connA->sendQuery('update $TTT set value = value + 25 where name = \'a\'');

        $connB->sendQuery('select * from $TTT where name = \'a\' for update');

        // here we read "not in transaction" - see condition below since which MySQL/MariaDB version the issue is no longer present
        $e = null;
        try {
            $connA->readResult();
        } catch (MysqlException $e) {
            // ERROR 1205 (HY000): Lock wait timeout exceeded; try restarting transaction
            // ERROR 1213 (40001): Deadlock found when trying to get lock; try restarting transaction
            self::assertContains($e->getCode(), [1205, 1213]);
        }
        self::assertNotNull($e);

        $connA->sendQuery('select * from $TTT where name = \'a\'');

        if (
            $connA->serverIsMariaDB
                ? ((version_compare($connA->serverVersion, '10.11.13') <= 0 && $connA->serverVersion !== '10.5.15') // maybe also != 10.5.14 which was unreleased by MariaDB
                    || (version_compare($connA->serverVersion, '11.0') >= 0 && version_compare($connA->serverVersion, '11.4.4') <= 0)
                    || (version_compare($connA->serverVersion, '11.5') >= 0 && version_compare($connA->serverVersion, '11.7') < 0))
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
     * Reported as https://jira.mariadb.org/browse/MDEV-37198 .
     */
    public function testIssueKillWithoutErrorAffectsTransaction(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);

        $reproduced = false;
        for ($i = 0; $i < 1_000; ++$i) {
            $connA = $this->createConnection($table);
            $connB = $this->createConnection($table);

            $connA->sendQuery('SET SESSION TRANSACTION ISOLATION LEVEL READ COMMITTED');
            $connA->readResult();

            $connA->sendQuery('start transaction');
            $connA->readResult();

            $connA->sendQuery('update $TTT set value = 10 where name != \'b\'');
            $connA->readResult();

            $connA->sendQuery('update $TTT set value = 20 where name = \'a\'');

            $connB->sendQuery('kill query ' . $connA->threadId);
            $e = null;
            try {
                $connA->readResult();
            } catch (MysqlException $e) {
                self::assertSame(1317, $e->getCode());
            }
            $connB->readResult();

            if ($e === null && !$connA->serverIsMariaDB) {
                $connA->sendQuery('select * from $TTT where name = \'a\' for update');
                $e2 = null;
                try {
                    $connA->readResult();
                } catch (MysqlException $e2) {
                    self::assertSame(1317, $e2->getCode());
                }
                // self::assertNotNull($e2);
            }

            $connA->sendQuery('select * from $TTT where name = \'a\' for update');
            try {
                $connA->readResult();
            } catch (Exception $e2) {
                self::assertSame('Tracked locked value does not match actual value', $e2->getMessage());

                if ($e === null) {
                    $reproduced = true;

                    break;
                }
            }

            gc_collect_cycles();
            do {
                $connB->sendQuery('show status where `variable_name` = \'Threads_connected\'');
                $res = $connB->readResult();
                $activeConnections = (int) $res->rows[0]['Value'];
            } while ($activeConnections > 50);
        }

        self::assertSame(
            $connA->serverIsMariaDB && ( // @phpstan-ignore variable.undefined
                (version_compare($connA->serverVersion, '10.6.19') >= 0 && version_compare($connA->serverVersion, '10.7') < 0) // @phpstan-ignore variable.undefined, variable.undefined
                || (version_compare($connA->serverVersion, '10.11.9') >= 0 && version_compare($connA->serverVersion, '10.12') < 0) // @phpstan-ignore variable.undefined, variable.undefined
                || (version_compare($connA->serverVersion, '11.1.6') >= 0 && version_compare($connA->serverVersion, '11.2') < 0) // @phpstan-ignore variable.undefined, variable.undefined
                || (version_compare($connA->serverVersion, '11.2.5') >= 0 && version_compare($connA->serverVersion, '11.3') < 0) // @phpstan-ignore variable.undefined, variable.undefined
                || version_compare($connA->serverVersion, '11.4') >= 0 // @phpstan-ignore variable.undefined
            ),
            $reproduced
        );
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
        $connB = $this->createConnection($table);

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
        $e = null;
        try {
            $res = $connA->readResult();
        } catch (MysqlException $e) {
            self::assertSame(1205, $e->getCode());
        }
        self::assertSame($isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE, $e !== null);
        if ($e === null) {
            self::assertSame([['name' => 'b', 'value' => $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_READ_UNCOMMITTED ? '10' : '200']], $res->rows);
        }

        $connB->sendQuery('commit');
        $connB->readResult();

        for ($i = 0; $i < 2; ++$i) {
            $isRepeatableReadMariaDb116 = $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_REPEATABLE_READ
                && $connA->serverIsMariaDB && version_compare($connA->serverVersion, '11.6') >= 0;
            $isSerializableMariaDb1183 = $isolationLevel === MysqlConnectionWithState::ISOLATION_LEVEL_SERIALIZABLE
                && $connA->serverIsMariaDB && version_compare($connA->serverVersion, '11.8.3') >= 0;

            $connA->sendQuery('select * from $TTT where name != \'b\' for update');
            $e = null;
            try {
                $res = $connA->readResult();
            } catch (MysqlException $e) {
                self::assertSame(1020, $e->getCode());
            }
            self::assertSame(($isRepeatableReadMariaDb116 || $isSerializableMariaDb1183) && $i === 0, $e !== null);
            if ($e === null) {
                self::assertSame([['name' => 'a', 'value' => '10']], $res->rows); // @phpstan-ignore variable.undefined
            }

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
        $connOther = $this->createConnection($table);

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
        $e = null;
        try {
            $connOther->readResult();
        } catch (MysqlException $e) {
            self::assertSame(1205, $e->getCode());
        }
        self::assertNotNull($e);
        self::assertSame(10, $conn->lockedValue);
        self::assertNull($connOther->lockedValue);
        if (version_compare($conn->serverVersion, $conn->serverIsMariaDB ? '10.6' : '8.0.28') < 0) { // https://jira.mariadb.org/browse/MDEV-36960
            self::assertGreaterThan(1 - 0.6, $e->elapsed);
            self::assertLessThan(1 + 2.5, $e->elapsed);
        } else {
            self::assertEqualsWithDelta(1.0, $e->elapsed, 0.6);
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

        $maxQueries = random_int(0, 10) === 0 ? \PHP_INT_MAX : 15;

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

                $tester = new Tester(function () use ($table) {
                    $conn = $this->createConnection($table);
                    $conn->enableDebugPrint = true;
                    $conn->enableAssertInTransactionUsingQuery = random_int(0, 3) === 0;

                    return $conn;
                }, random_int(0, 10) === 0 ? 3 : 2);
                $tester->run(5.0, $maxQueries, random_int(0, 10) === 0);

                ob_end_clean();
            } catch (\Throwable $e) {
                ob_end_flush();

                throw $e;
            }

            if ($ts > $lastDumpTs + 20) {
                $lastDumpTs = $ts;
                echo '==== elapsed ' . round($ts - $startTs) . ' s, iter ' . $i . ' ================' . "\n";
            }
        }
    }
}
