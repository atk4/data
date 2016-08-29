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
     * Field type.
     *
     * Values are: 'string', 'boolean', 'bool', 'integer', 'int', 'money',
     *             'float', 'date', 'datetime', 'time', 'array'.
     * Can also be set to unspecified type for your own custom handling.
     *
     * @var string
     */
    public $type = 'string';

    /**
     * For several types enum can provide list of available options.
     *
     * @var array|null
     */
    public $enum = null;

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
     * System fields will be always loaded and saved.
     *
     * @var bool
     */
    public $system = false;

    /**
     * Setting this to true will never actually load or store
     * the field in the database. It will action as normal,
     * but will be skipped by load/iterate/update/insert.
     *
     * @var bool
     */
    public $never_persist = false;

    /**
     * Setting this to true will never actually store
     * the field in the database. It will action as normal,
     * but will be skipped by update/insert.
     *
     * @var bool
     */
    public $never_save = false;

    /**
     * Is field read only?
     * Field value may not be changed. It'll never be saved.
     * For example, expressions are read only.
     *
     * @var bool
     */
    public $read_only = false;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Is field mandatory? By default fields are not mandatory.
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Define callback to execute after loading value for this field
     * from the database.
     */
    public $loadCallback = null;

    /**
     * Define callback to execute before saving value for this field
     * to the database.
     */
    public $saveCallback = null;

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
            if (is_array($val)) {
                $this->$key = array_merge(isset($this->$key) && is_array($this->$key) ? $this->$key : [], $val);
            } else {
                $this->$key = $val;
            }
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

        return $this;
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

    /**
     * Returns if field should be editable in UI.
     *
     * @return bool
     */
    public function isEditable()
    {
        return $this->read_only || $this->never_persist
            ? false
            : (isset($this->ui['editable']) ? $this->ui['editable'] : !$this->system);
    }

    /**
     * Returns if field should be visible in UI.
     *
     * @return bool
     */
    public function isVisible()
    {
        return isset($this->ui['visible']) ? $this->ui['visible'] : !$this->system;
    }

    /**
     * Returns if field should be hidden in UI.
     *
     * @return bool
     */
    public function isHidden()
    {
        return isset($this->ui['hidden']) ? $this->ui['hidden'] : false;
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

        foreach ([
            'type', 'system', 'never_persist', 'never_save', 'read_only', 'ui', 'join',
        ] as $key) {
            if (isset($this->$key)) {
                $arr[$key] = $this->$key;
            }
        }

        return $arr;
    }

    // }}}
}
