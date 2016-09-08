<?php

namespace atk4\data\tests;

use atk4\data\Model;

class Model_Male extends Model_Person {
    function init() {
        parent::init();
        $this->addCondition('gender', 'M');
    }
}
