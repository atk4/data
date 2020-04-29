<?php declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

class FieldHereditaryTest extends \atk4\schema\PhpunitTestCase
{
    public function testDirty1()
    {
        $p = new Persistence\Static_(['hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->addExpression('caps', function ($m) {
            return strtoupper($m->get('name'));
        });

        $m->load(1);
        $this->assertSame('world', $m->get('name'));
        $this->assertSame('WORLD', $m->get('caps'));
    }
}
