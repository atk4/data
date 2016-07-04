<?php

namespace atk4\data;

class Field {
    use \atk4\core\TrackableTrait;
    use \atk4\core\HookTrait;

    public $default = null;

    public $type = 'string';

    public $actual = null;

    public $join = null;

    function __construct($defaults = []) {

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    public function get()
    {
        return $this->owner[$this->short_name];
    }

    function setAttr($attr, $value)
    {
        $this->$attr = $value;
        return $this;
    }

    function getAttr($attr)
    {
        return $this->$attr;
    }

    public function __debugInfo()
    {
        $object = (array)$this;
        unset($object['owner']);

        foreach($object as $key=>$val){
            if ($val === null) {
                unset($object[$key]);
                continue;
            }

            if ($key[0] == '_') {
                unset($object[$key]);
            }
        }

        return $object;
    }
}

