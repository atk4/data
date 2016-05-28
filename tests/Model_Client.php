<?php
namespace atk4\data\tests;
use atk4\data\Model;

class Model_Client extends Model_User {
    function init() {
        parent::init();

        $this->addField('order', ['default'=>'10']);
    }
}
