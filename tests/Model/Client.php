<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

class Client extends User
{
    protected function init(): void
    {
        parent::init();

        $this->addField('order', ['default' => '10']);
    }
}
