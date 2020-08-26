<?php

declare(strict_types=1);

namespace atk4\data\tests\Model\Smbo;

class Contact extends \atk4\data\Model
{
    public $table = 'contact';

    protected function init(): void
    {
        parent::init();

        $this->addField('type', ['enum' => ['client', 'supplier']]);

        $this->addField('name');
    }
}
