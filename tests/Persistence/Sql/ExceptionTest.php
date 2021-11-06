<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Expression;

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Exception
 */
class ExceptionTest extends TestCase
{
    /**
     * @covers ::__construct
     */
    public function testException1(): void
    {
        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);

        throw new \Atk4\Data\Persistence\Sql\Exception();
    }

    public function testException2(): void
    {
        $this->expectException(\Atk4\Data\Persistence\Sql\Exception::class);
        $e = new Expression('hello, [world]');
        $e->render();
    }

    public function testException3(): void
    {
        try {
            $e = new Expression('hello, [world]');
            $e->render();
        } catch (\Atk4\Data\Persistence\Sql\Exception $e) {
            $this->assertSame(
                'Expression could not render tag',
                $e->getMessage()
            );

            $this->assertSame(
                'world',
                $e->getParams()['tag']
            );
        }
    }
}
