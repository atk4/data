<?php

namespace atk4\data\tests;

use atk4\data\Model;

class RefactoredFieldTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    // === Field_Boolean tests ================================================

    public function testBoolean()
    {
        $m = new Model();
        $m->addField('is_vip_1', ['type' => 'boolean', 'enum' => ['No', 'Yes']]);
        $m->addField('is_vip_2', ['type' => 'boolean', 'valueTrue' => 1, 'valueFalse' => 0]);
        $m->addField('is_vip_3', ['type' => 'boolean', 'valueTrue' => 'Y', 'valueFalse' => 'N']);

        $m->set('is_vip_1', 'No');
        $this->assertEquals(false, $m['is_vip_1']);
        $m->set('is_vip_1', 'Yes');
        $this->assertEquals(true, $m['is_vip_1']);
        $m->set('is_vip_1', false);
        $this->assertEquals(false, $m['is_vip_1']);
        $m->set('is_vip_1', true);
        $this->assertEquals(true, $m['is_vip_1']);
        $m->set('is_vip_1', 0);
        $this->assertEquals(false, $m['is_vip_1']);
        $m->set('is_vip_1', 1);
        $this->assertEquals(true, $m['is_vip_1']);

        $m->set('is_vip_2', 0);
        $this->assertEquals(false, $m['is_vip_2']);
        $m->set('is_vip_2', 1);
        $this->assertEquals(true, $m['is_vip_2']);
        $m->set('is_vip_2', false);
        $this->assertEquals(false, $m['is_vip_2']);
        $m->set('is_vip_2', true);
        $this->assertEquals(true, $m['is_vip_2']);

        $m->set('is_vip_3', 'N');
        $this->assertEquals(false, $m['is_vip_3']);
        $m->set('is_vip_3', 'Y');
        $this->assertEquals(true, $m['is_vip_3']);
        $m->set('is_vip_3', false);
        $this->assertEquals(false, $m['is_vip_3']);
        $m->set('is_vip_3', true);
        $this->assertEquals(true, $m['is_vip_3']);
    }

    // === Field/Numeric tests ================================================

    public function testNumeric()
    {
        $m = new Model();
        $m->addField('n1', ['type' => 'float']);
        $m->addField('n2', ['type' => 'float', 'required' => true, 'signum' => false]);
        $m->addField('n3', ['type' => 'float', 'min' => 18, 'max' => 99, 'decimals' => 2]);

        $m->set('n1', null);
        $this->assertEquals(null, $m['n1']);
        $m->set('n1', 0);
        $this->assertEquals(0, $m['n1']);

        $m->set('n2', 1.2345);
        $this->assertEquals(1.2345, $m['n2']);

        $m->set('n3', 20.345678);
        $this->assertEquals(20.35, $m['n3']); // rounding
    }

    // === Field/Integer tests ================================================

    public function testInteger()
    {
        $m = new Model();
        $m->addField('n', ['type' => 'integer']);

        $m->set('n', null);
        $this->assertEquals(null, $m['n']);
        $m->set('n', 0);
        $this->assertEquals(0, $m['n']);
        $m->set('n', 1.2345);
        $this->assertEquals(1, $m['n']);
        $m->set('n', 1.5678);
        $this->assertEquals(1, $m['n']); // no rounding
    }

    // === Field/Money tests ==================================================

    public function testMoney()
    {
        $m = new Model();
        $m->addField('n', ['type' => 'money']);

        $m->set('n', null);
        $this->assertEquals(null, $m['n']);
        $m->set('n', 0);
        $this->assertEquals(0, $m['n']);
        $m->set('n', 1.12345);
        $this->assertEquals(1.12, $m['n']);
        $m->set('n', -1.12345);
        $this->assertEquals(-1.12, $m['n']);
    }
}
