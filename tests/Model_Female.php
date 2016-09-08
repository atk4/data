<?php

namespace atk4\data\tests;

use atk4\data\Model;

class Model_Female extends Model_Person {
    function init() {
        parent::init();
        $this->addCondition('gender', 'F');
    }
}
