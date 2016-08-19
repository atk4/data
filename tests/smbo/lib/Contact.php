<?php

namespace atk4\data\tests\smbo;

class Contact extends \atk4\data\Model {
    public $table='contact';

    function init()
    {
        parent::init();

        $this->addField('type', ['enum'=>['client','supplier']]);

        $this->addField('name');
    }
}
