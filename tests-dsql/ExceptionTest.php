<?php

declare(strict_types=1);

namespace Atk4\Dsql\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Dsql\Expression;

/**
 * @coversDefaultClass \Atk4\Dsql\Exception
 */
class ExceptionTest extends AtkPhpunit\TestCase
{
    /**
     * @covers ::__construct
     */
    public function testException1(): void
    {
        $this->expectException(\Atk4\Dsql\Exception::class);

        throw new \Atk4\Dsql\Exception();
    }

    public function testException2(): void
    {
        $this->expectException(\Atk4\Dsql\Exception::class);
        $e = new Expression('hello, [world]');
        $e->render();
    }

    public function testException3(): void
    {
        try {
            $e = new Expression('hello, [world]');
            $e->render();
        } catch (\Atk4\Dsql\Exception $e) {
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
