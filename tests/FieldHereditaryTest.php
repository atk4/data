<?php

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
            return mb_strtoupper($m['name']);
        });

        $m->load(1);
        $this->assertSame('world', $m['name']);
        $this->assertSame('WORLD', $m['caps']);
    }
}
