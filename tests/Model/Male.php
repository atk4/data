<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

class Male extends Person
{
    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addCondition('gender', 'M');
    }
}
