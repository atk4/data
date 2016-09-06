<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ConditionTest extends TestCase
{

    /**
     * @expectedException Exception
     */
    public function testException1()
    {
        // not existing field in condition
        $m = new Model();
        $m->addField('name');
        $m->addCondition('last_name', 'Smith');
    }

    public function testBasicDiscrimination()
    {
        $m = new Model();
        $m->addField('name');

        $m->addField('gender', ['enum' => ['M', 'F']]);
        $m->addField('foo');

        $m->addCondition('gender', 'M');

        $this->assertEquals(1, count($m->conditions));

        $m->addCondition('gender', 'F');

        $this->assertEquals(2, count($m->conditions));

        $m->addCondition([['gender', 'F'], ['foo', 'bar']]);
        $this->assertEquals(4, count($m->conditions));
    }
}
