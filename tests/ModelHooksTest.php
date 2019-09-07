<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ModelHooksTest extends \atk4\schema\PHPUnit_SchemaTestCase
{

    public $m;

    public function setUp()
    {
        parent::setUp();

        $a = [
            'user' => [
                1 => ['name' => 'John', 'gender' => 'M'],
            ],
        ];

        $this->setDB($a);

        $db = new Persistence\SQL($this->db->connection);

        $this->m = new Model($db, 'user');
        $this->m->addFields(['name', 'gender']);
    }

    public function testBeforeSaveIsUpdate()
    {
        $this->m->addHook(
            'beforeSave', function ($m, $is_update) {
                if ($is_update) {
                    $m->set('name', 'UpdatedModel');
                } else {
                    $m->set('name', 'InsertedModel');
                }
            });

        $this->m->save();
        $this->assertEquals('InsertedModel', $this->m->get('name'));

        $this->m->save([
            'gender' => 'trigger_save_with_changed_data',
        ]);
        $this->assertEquals('UpdatedModel', $this->m->get('name'));
    }

    public function testAfterSaveIsUpdate()
    {
        $this->m->addHook(
            'afterSave', function ($m, $is_update) {
                if ($is_update) {
                    $m->set('name', 'UpdatedModel');
                } else {
                    $m->set('name', 'InsertedModel');
                }
            });

        $this->m->save();
        $this->assertEquals('InsertedModel', $this->m->get('name'));

        $this->m->save([
            'gender' => 'trigger_save_with_changed_data',
        ]);
        $this->assertEquals('UpdatedModel', $this->m->get('name'));
    }
}
