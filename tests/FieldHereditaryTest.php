<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

class FieldHereditaryTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testDirty1()
    {
        $p = new Persistence\Static_(['hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->addExpression('caps', function ($m) {
            return strtoupper($m['name']);
        });

        $m->load(1);
        $this->assertEquals('world', $m['name']);
        $this->assertEquals('WORLD', $m['caps']);
    }
}
