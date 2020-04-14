<?php

namespace atk4\data\tests\Model;

class Male extends Person
{
    public function init(): void
    {
        parent::init();
        $this->addCondition('gender', 'M');
    }
}
