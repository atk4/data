<?php

namespace atk4\data\tests;

use atk4\data\Model;

class DefaultTest extends \atk4\schema\PhpunitTestCase
{
    public function testDefaultValue()
    {
        $m = new Model();
        $m->addField('nodefault');
        $m->addField('withdefault', ['default' => 'abc']);

        $this->assertNull($m->get('nodefault'));
        $this->assertEquals('abc', $m->get('withdefault'));

        $this->assertNull($m->getField('nodefault')->get());
        $this->assertEquals('abc', $m->getField('withdefault')->get());
    }
}
