<?php

namespace atk4\data\tests\Model;

use atk4\data\Model;

class Person extends Model
{
    public $table = 'person';

    public function init()
    {
        parent::init();
        $this->addField('name');
        $this->addField('surname');
        $this->addField('gender', ['enum' => ['M', 'F']]);
    }
}
