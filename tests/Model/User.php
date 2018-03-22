<?php

namespace atk4\data\tests\Model;

use atk4\data\Model;

class User extends Model
{
    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('surname');
    }
}
