<?php

namespace atk4\data;

class Field {
    use \atk4\core\TrackableTrait;
    use \atk4\core\HookTrait;

    public $default = null;

    public $type = 'string';

    function __construct($defaults = []) {

        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    public function getDefault()
    {
        return $this->default;
    }
}

