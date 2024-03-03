<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model;

class Person extends Model2
{
    public $table = 'person';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M', 'F']]);
    }
}
