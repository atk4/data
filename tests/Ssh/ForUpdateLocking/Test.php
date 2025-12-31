<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Core\Phpunit\TestCase;
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

    public function testRunForUpdateTester(): void
    {
        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType

        $table = $this->makeRandomTableName();
        $tester = new Tester(fn () => $this->createConnection($table), 3);
        $tester->run(90.0);
    }
}
