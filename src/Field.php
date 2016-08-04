<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Field
{
    use \atk4\core\TrackableTrait;
    use \atk4\core\HookTrait;

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default = null;

    /**
     * Field type, for example, 'string', 'boolean', 'numeric', 'int', 'date' etc.
     *
     * @var string
     */
    public $type = 'string';

    /**
     * Actual field name.
     *
     * @var string|null
     */
    public $actual = null;

    /**
     * Join object.
     *
     * @var Join|null
     */
    public $join = null;

    /**
     * Is it system field?
     *
     * @var bool
     */
    public $system = false;

    /**
     * Is field editable? Normally you can edit fields.
     *
     * @var bool
     */
    public $editable = true;

    /**
     * Is field mandatory? By default fields are not mandatory.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     */
    public $never_persist = false;

    /**
     * Constructor. You can pass field properties as array.
     *
     * @param array $defaults
     */
    public function __construct($defaults = [])
    {
        if (!is_array($defaults)) {
            throw new Exception(['Field requires array for defaults', 'arg' => $defaults]);
        }
        foreach ($defaults as $key => $val) {
            $this->$key = $val;
        }
    }

    /**
     * Returns field value.
     *
     * @return mixed
     */
    public function get()
    {
        return $this->owner[$this->short_name];
    }

    /**
     * Sets field value.
     *
     * @param mixed $value
     *
     * @return $this
     */
    public function set($value)
    {
        $this->owner[$this->short_name] = $value;

        return$this;
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
     * Returns array with useful debug info for var_dump.
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

        if ($this->editable) {
            $arr['editable'] = $this->editable;
        }

        return $arr;
    }

    // }}}
}
