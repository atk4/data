<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Exception as SqlException;
use Atk4\Data\Persistence\Sql\Expression;

class ExceptionTest extends TestCase
{
    public function testException1(): void
    {
        $this->expectException(SqlException::class);

        throw new SqlException();
    }

    public function testException2(): void
    {
        $e = new Expression('hello, [world]');

        $this->expectException(SqlException::class);
        $e->render();
    }

    public function testException3(): void
    {
        try {
            $e = new Expression('hello, [world]');
            $e->render();
        } catch (SqlException $e) {
            $this->assertSame('Expression could not render tag', $e->getMessage());
            $this->assertSame('world', $e->getParams()['tag']);
        }
    }
}
