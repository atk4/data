<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;

class FieldHereditaryTest extends TestCase
{
    public function testDirty1(): void
    {
        $p = new Persistence\Static_(['hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->addExpression('caps', function ($m) {
            return strtoupper($m->get('name'));
        });

        $m = $m->load(1);
        $this->assertSame('world', $m->get('name'));
        $this->assertSame('WORLD', $m->get('caps'));
    }
}
