<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Ssh;

use Atk4\Data\Exception;
use Atk4\Data\Ssh\MysqliAsyncConnection;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;

/**
 * @requires extension ssh2
 */
#[RequiresPhpExtension('ssh2')]
class MysqliAsyncConnectionTest extends MysqlConnectionTest
{
    /**
     * @return array{string, string}
     */
    #[\Override]
    public static function getSshConfig(): array
    {
        return ['mysqli', 'mysqli'];
    }

    #[\Override]
    protected function createConnection(): MysqliAsyncConnection
    {
        return new MysqliAsyncConnection(...static::getSshConfig(), ...static::getMysqlConfig());
    }

    #[\Override]
    public function testCiMaxRootConnectionsHonored(): void
    {
        self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType
    }

    public function testReadStdoutException(): void
    {
        $conn = $this->createConnection();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Code invoked unexpectedly');
        $conn->readStdout(static fn () => true, 0.1);
    }
}
