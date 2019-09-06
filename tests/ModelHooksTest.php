<?php

namespace atk4\data\tests;

use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ModelHooksTest extends \atk4\core\PHPUnit_AgileTestCase
{

    /**
     *
     */
    public function testBeforeSaveIsUpdate() {
        $model = new Model($this->db);
        $model->addHook('beforeSave', function($m, $is_update) {
            if($is_update) {
                $m->title = 'UpdatedModel';
            }
            else {
                $m->title = 'InsertedModel';
            }
        });

        $model->save();
        $this->assertEquals('InsertedModel', $model->title);

        $model->save();
        $this->assertEquals('UpdatedModel', $model->title);
    }


    /**
     *
     */
    public function testAfterSaveIsUpdate() {
        $model = new Model($this->db);
        $model->addHook('afterSave', function($m, $is_update) {
            if($is_update) {
                $m->title = 'UpdatedModel';
            }
            else {
                $m->title = 'InsertedModel';
            }
        });

        $model->save();
        $this->assertEquals('InsertedModel', $model->title);

        $model->save();
        $this->assertEquals('UpdatedModel', $model->title);
    }
}