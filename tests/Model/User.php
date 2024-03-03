<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Model;

use Atk4\Data\Model2;

class User extends Model2
{
    #[\Override]
    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
    }
}
