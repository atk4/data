<?php

namespace atk4\data;

class Field
{
    use \atk4\core\TrackableTrait;
    use \atk4\core\HookTrait;

    public $default = null;

    /**
     * Field type, for example, 'string', 'boolean', 'numeric' etc.
     *
     * @var string
     */
    public $type = 'string';

    /**
     * Actual field name
     *
     * @var string|null
     */
    public $actual = null;

    public $join = null;

    /**
     * Is it system field?
     *
     * @var boolean
     */
    public $system = false;

    /**
     * Is field editable? Normally you can edit fields.
     *
     * @var boolean
     */
    public $editable = true;

    /**
     * Constructor. You can pass field properties as array.
     *
     * @param array $defaults
     */
    public function __construct($defaults = [])
    {
        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Returns this field object.
     *
     * @return Field
     */
    public function get()
    {
        return $this->owner[$this->short_name];
    }

    /**
     * Sets field attribute value.
     *
     * If you can, use $this->$attr = foo instead of this method. No magic.
     *
     * @param string $attr  Attribute name
     * @param mixed  $value Attribute value
     *
     * @return $this
     */
    public function setAttr($attr, $value)
    {
        $this->$attr = $value;

        return $this;
    }

    // {{{ Debug Methods
    /**
     * Returns array with useful info for debugging.
     *
     * @return array
     */
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
