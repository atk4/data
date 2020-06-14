<?php

declare(strict_types=1);

namespace atk4\data\tests\Model;

use atk4\data\Model;

class User extends Model
{
    public function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
    }
}
