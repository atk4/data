<?php

declare(strict_types=1);

namespace atk4\data\Tests\Model;

class Female extends Person
{
    protected function init(): void
    {
        parent::init();
        $this->addCondition('gender', 'F');
    }
}
