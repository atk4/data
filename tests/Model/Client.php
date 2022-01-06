<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

class Client extends User
{
    public $table = 'client';

    protected function init(): void
    {
        parent::init();

        $this->addField('order', ['default' => '10']);
    }
}
