<?php

namespace atk4\data;

class Field
{
    use \atk4\core\TrackableTrait;
    use \atk4\core\HookTrait;

    public $default = null;

    public $type = 'string';

    public $actual = null;

    public $join = null;

    public $system = false;

    // normally you can edit fields
    public $editable = true;

    public function __construct($defaults = [])
    {
        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    public function get()
    {
        return $this->owner[$this->short_name];
    }

    /**
     * if you can, use $this->$attr = foo instead of this method. No magic.
     */
    public function setAttr($attr, $value)
    {
        $this->$attr = $value;

        return $this;
    }

    // {{{ Debug Methods
    public function __debugInfo()
    {
        $arr = [
            'short_name' => $this->short_name,
            'value'      => $this->get(),
        ];

        if ($this->type) {
            $arr['type'] = $this->type;
        }

        if ($this->system) {
            $arr['system'] = $this->system;
        }

        if ($this->join) {
            $arr['join'] = $this->join;
        }

        return $arr;
    }

    // }}}
}
