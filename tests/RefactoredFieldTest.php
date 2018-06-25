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
}
