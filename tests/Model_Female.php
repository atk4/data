<?php

namespace atk4\data\tests;

class Model_Female extends Model_Person
{
    public function init()
    {
        parent::init();
        $this->addCondition('gender', 'F');
    }
}
