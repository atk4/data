<?php

namespace atk4\data\tests;

use atk4\data\Model;

class Model_Person extends Model {
    function init() {
        parent::init();
        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M', 'F']]);
    }
}
