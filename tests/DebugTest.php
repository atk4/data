<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Schema\TestSqlPersistence;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class DebugTest extends TestCase
{
    /**
     * @param string|array<string, mixed> $template
     * @param array<int|string, mixed>    $arguments
     */
    protected function e($template = [], array $arguments = []): Expression
    {
        return $this->getConnection()->expr($template, $arguments);
    }

    public function testExpression(): void
    {
        // PostgreSQL, at least versions before 10, needs to have the string cast to the correct datatype.
        // But using CAST(.. AS CHAR) will return a single character on PostgreSQL, but the entire string on MySQL.
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSame(
                'foo',
                $this->e('select CAST([] AS VARCHAR)', ['foo'])->getOne()
            );
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame(
                'foo',
                $this->e('select CAST([] AS VARCHAR2(100)) FROM DUAL', ['foo'])->getOne()
            );
        } else {
            self::assertSame(
                'foo',
                $this->e('select CAST([] AS CHAR)', ['foo'])->getOne()
            );
        }
    }

    public function test10ParallelConnections(): void
    {
        $connections = [];

        for ($i = 0; $i < 10; ++$i) {
            $this->db = new TestSqlPersistence();
            $connections[] = $this->db;

            fwrite(\STDERR, 'p>');
            $this->testExpression();
            fwrite(\STDERR, ".\n");
        }
    }

    public function test10kSerialConnections(): void
    {
        for ($i = 0; $i < 10_000; ++$i) {
            $this->db = new TestSqlPersistence();
            gc_collect_cycles();

            if (($i % 100) === 0) {
                fwrite(\STDERR, 's>');
            }
            $this->testExpression();
            if (($i % 100) === 0) {
                fwrite(\STDERR, ".\n");
            }
        }
    }
}
