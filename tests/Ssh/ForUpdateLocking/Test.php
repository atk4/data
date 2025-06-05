<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqlConnection;
use Atk4\Data\Tests\Ssh\MysqlConnectionTest;
use Atk4\Data\Tests\Ssh\MysqliAsyncConnectionTest;
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

        $conn->sendQuery('insert into $TTT values (\'a\', 100), (\'b\', 100)');
        $conn->readResult();
    }

    public function testIssueTransactionTemporaryTurnedOffAfterLockInShareMode(): void
    {
        $table = $this->makeRandomTableName();
        $this->createTestTable($table);
        $connA = $this->createConnection($table);
        $connA->enableDebugPrint = false;
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
            $this->expectException(Exception::class); // later convert to dedicated exception!
            $this->expectExceptionMessage('Wrong "inTransaction" assumed');
        }

        // here we read "in transaction"
        $connA->readResult();

        // not needed
        // $connB->readResult();

        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    public function testRunForUpdateTester(): void
    {
        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType

        $table = $this->makeRandomTableName();
        $tester = new Tester(fn () => $this->createConnection($table), 3);
        $tester->run(90.0);
    }
}
