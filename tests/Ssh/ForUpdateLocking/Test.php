<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh\ForUpdateLocking;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Tests\Ssh\MysqlConnectionTest;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * @requires extension ssh2
 */
#[RequiresPhpExtension('ssh2')]
class Test extends TestCase
{
    protected function createConnection(): ConnectionWithValue
    {
        $conn = new ConnectionWithValue(...MysqlConnectionTest::getSshConfig(), ...MysqlConnectionTest::getMysqlConfig());
        $conn->enableDebugPrint = true;

        return $conn;
    }

    public function testRunForUpdateTester(): void
    {
        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType

        $tester = new Tester(fn () => $this->createConnection(), 3);
        $tester->run(90.0);
    }
}
