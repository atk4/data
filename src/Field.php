<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
class Field
{
    use \atk4\core\TrackableTrait;
//    use \atk4\core\HookTrait;

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
    public $type = null;

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
     * Depending on the type of a current field, this will perform
     * some normalization for strict types.
     *
     * @param mixed $value
     *
     * @return mixed
     */
    public function normalize($value)
    {
        if (!$this->owner->strict_types) {
            return $value;
        }
        if ($value === null) {
            return;
        }
        $f = $this;

        switch ($f->type) {
        case 'string':
            if (!is_string($value) && !is_numeric($value)) {
                throw new Exception('Field value must be a string');
            }
            $value = trim($value);
            break;
        case 'boolean':
            if (is_bool($value)) {
                break;
            }
            if (isset($f->enum) && is_array($f->enum)) {
                if (isset($f->enum[0]) && $value === $f->enum[0]) {
                    $value = true;
                } elseif (isset($f->enum[1]) && $value === $f->enum[1]) {
                    $value = false;
                }
            }
            if (!is_bool($value)) {
                throw new Exception('Field value must be a boolean');
            }
            break;
        case 'money':
            if (!is_numeric($value)) {
                throw new Exception('Field value must be numeric');
            }
            $value = round($value, 4);
            break;
        case 'date':
        case 'datetime':
        case 'time':
            $class = isset($f->dateTimeClass) ? $f->dateTimeClass : 'DateTime';

            if (is_numeric($value)) {
                $value = new $class('@'.$value);
            } elseif (is_string($value)) {
                $value = new $class($value);
            } elseif (!$value instanceof $class) {
                throw new Exception('Field value must be a '.$f->type);
            }
            break;
        case 'integer':
            if (!is_numeric($value)) {
                throw new Exception('Field value must be an integer');
            }
            $value = (int) $value;
            break;
        case 'float':
            if (!is_numeric($value)) {
                throw new Exception('Field value must be a float');
            }
            $value = (float) $value;
            break;
        case 'struct':
            // Can be pretty-much anything, but not object
            if (is_object($value)) {
                throw new Exception('Field value must be a struct');
            }
            break;
        case 'int':
        case 'bool':
        case 'str':
            throw new Exception([
                'Use of obsolete field type abbreviation. Use "integer", "string", "boolean" etc.',
                'type' => $f->type,
            ]);
            break;
        }

        return $value;
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
