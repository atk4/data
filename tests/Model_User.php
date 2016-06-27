<?php
namespace atk4\data\tests;
use atk4\data\Model;

class Model_User extends Model {
    function init() {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
    }
}
