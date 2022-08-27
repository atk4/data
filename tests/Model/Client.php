<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

class Client extends User
{
    public $table = 'client'; // @phpstan-ignore-line https://github.com/phpstan/phpstan/issues/7839

    protected function init(): void
    {
        parent::init();

        $this->addField('order', ['type' => 'integer', 'default' => 10]);
    }
}
