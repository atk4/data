<?php
namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ConditionTest extends TestCase
{

    public function testBasicDiscrimination()
    {
        $m = new Model();
        $m->addField('name');

        $m->addField('gender', ['enum'=>['M','F']]);

        $m->addCondition('gender','M');

        $this->assertEquals(1, count($m->conditions));

        $m->addCondition('gender','F');

        $this->assertEquals(2, count($m->conditions));

        $m->addCondition([['gender','F'], ['foo','bar']]);
        $this->assertEquals(4, count($m->conditions));

    }

}
