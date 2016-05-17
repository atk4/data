<?php

namespace atk4\data;

class Model {
    use \atk4\core\ContainerTrait;
//    use DebugTrait;
//    use HookTrait;
//    use InitializerTrait;
    protected $field_class = 'atk4\data\Field';

    /**
     * Persistance driver
     */
    public $connection;

    //private $field_class = 'atk4\data\Field';

    function __construct()
    {
    }

    function init()
    {
    }


    function addField($name)
    {
        $c = $this->field_class;
        $field = new $c($name);
        $this->add($field, $name);
        return $field;
    }

    /**
     * Generic addition field for popilating fields
     *
     * @return Field
     */
    function add($name, $options = null)
    {
        $this->_add_Container($name, $options);
    }

}
