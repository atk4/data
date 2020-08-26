<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

class Male extends Person
{
    protected function init(): void
    {
        parent::init();
        $this->addCondition('gender', 'M');
    }
}
