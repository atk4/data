<?php

namespace atk4\data\tests;

class FieldHereditaryTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testDirty1()
    {
        $p = new \atk4\data\Persistence_Static(['hello', 'world']);

        // default title field
        $m = new \atk4\data\Model($p);
        $m->addField('caps', ['Callback', function ($m) {
            return strtoupper($m['name']);
        }]);

        $m->load(1);
        $this->assertEquals('world', $m['name']);
        $this->assertEquals('WORLD', $m['caps']);
    }
}
