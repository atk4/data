<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\core\TrackableTrait;

/**
 * Class description?
 */
class Field
{
    use TrackableTrait;

    // {{{ Properties

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default = null;

    /**
     * Field type.
     *
     * Values are: 'string', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
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
     * Defines a label to go along with this field. Use getCaption() which
     * will always return meaningfull label (even if caption is null). Set
     * this property to any string.
     *
     * @var string
     */
    public $caption = null;

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
     * Should we use typecasting when saving/loading data to/from persistence.
     *
     * Value can be array [$typecast_save_callback, $typecast_load_callback].
     *
     * @var null|bool|array
     */
    public $typecast = null;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array [$encode_callback, $decode_callback].
     *
     * @var null|bool|array
     */
    public $serialize = null;

    /**
     * Persisting format for type = 'date', 'datetime', 'time' fields.
     *
     * For example, for date it can be 'Y-m-d', for datetime - 'Y-m-d H:i:s' etc.
     *
     * @var string
     */
    public $persist_format = null;

    /**
     * Persisting timezone for type = 'date', 'datetime', 'time' fields.
     *
     * For example, 'IST', 'UTC', 'Europe/Riga' etc.
     *
     * @var string
     */
    public $persist_timezone = 'UTC';

    /**
     * DateTime class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTime', 'Carbon' etc.
     *
     * @param string
     */
    public $dateTimeClass = 'DateTime';

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon' etc.
     *
     * @param string
     */
    public $dateTimeZoneClass = 'DateTimeZone';

    // }}}

    // {{{ Core functionality

    /**
     * Constructor. You can pass field properties as array.
     *
     * @param array $defaults
     *
     * @throws Exception
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
     * @throws Exception
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

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($f->type && $f->type != 'string' && $value === '') {
            return;
        }

        switch ($f->type) {
        case 'string':
            if (!is_scalar($value)) {
                throw new Exception('Field value must be a string');
            }
            $value = trim($value);
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
        case 'money':
            if (!is_numeric($value)) {
                throw new Exception('Field value must be numeric');
            }
            $value = round($value, 4);
            break;
        case 'boolean':
            if (is_bool($value)) {
                break;
            }
            if (isset($f->enum) && is_array($f->enum)) {
                if (isset($f->enum[0]) && $value === $f->enum[0]) {
                    $value = false;
                } elseif (isset($f->enum[1]) && $value === $f->enum[1]) {
                    $value = true;
                }
            } elseif (is_numeric($value)) {
                $value = (bool) $value;
            }
            if (!is_bool($value)) {
                throw new Exception('Field value must be a boolean');
            }
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
                throw new Exception(['Field value must be a '.$f->type, 'class' => $class, 'value class' => get_class($value)]);
            }
            break;
        case 'array':
            if (!is_array($value)) {
                throw new Exception('Field value must be a array');
            }
            break;
        case 'object':
            if (!is_object($value)) {
                throw new Exception('Field value must be a object');
            }
            break;
        case 'int':
        case 'str':
        case 'bool':
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

    // }}}

    // {{{ Handy methods used by UI

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

    public function getCaption()
    {
        return isset($this->caption) ? $this->ui['caption'] :
            ucwords(str_replace('_', ' ', $this->short_name));
    }

    // }}}

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
