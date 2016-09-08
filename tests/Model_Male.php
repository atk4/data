<?php

namespace atk4\data\tests;

class Model_Male extends Model_Person
{
    public function init()
    {
        parent::init();
        $this->addCondition('gender', 'M');
    }
}
