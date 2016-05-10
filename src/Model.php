<?php

namespace atk4\dsql;

use atk4\core;

class Model {
    use ContainerTrait;
    use DebugTrait;
    use HookTrait;
    use InitializerTrait;

    /**
     * Persistance driver
     */
    public $connection;

    private $field_class = 'atk4\data\Field';

    

    function init() {
    }


    function addField(){
    }

    function addCondition() {
    }

}
