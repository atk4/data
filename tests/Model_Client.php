<?php

namespace atk4\data\tests;

class Model_Client extends Model_User
{
    public function init()
    {
        parent::init();

        $this->addField('order', ['default' => '10']);
    }
}
