<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ConditionTest extends \atk4\core\PHPUnit_AgileTestCase
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
        $this->assertEquals(3, count($m->conditions));
    }

    public function testEditableAfterCondition()
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('gender');
        $m->addCondition('gender', 'M');

        $this->assertEquals(true, $m->getElement('gender')->system);
        $this->assertEquals(false, $m->getElement('gender')->isEditable());
    }

    public function testEditableHasOne()
    {
        $gender = new Model();
        $gender->addField('name');

        $m = new Model();
        $m->addField('name');
        $m->hasOne('gender_id', $gender);

        $this->assertEquals(false, $m->getElement('gender_id')->system);
        $this->assertEquals(true, $m->getElement('gender_id')->isEditable());
    }
}
