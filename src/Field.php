<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\core\DIContainerTrait;
use atk4\core\ReadableCaptionTrait;
use atk4\core\TrackableTrait;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

/**
 * Class description?
 */
class Field implements Expressionable
{
    use TrackableTrait;
    use DIContainerTrait;
    use ReadableCaptionTrait;

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
     * Values are:
     *      'string', 'text', 'boolean', 'integer', 'money', 'float',
     *      'date', 'datetime', 'time', 'array', 'object'.
     * Can also be set to unspecified type for your own custom handling.
     *
     * @var string
     */
    public $type = null;

    /**
     * For several types enum can provide list of available options. ['blue', 'red'].
     *
     * @var array|null
     */
    public $enum = null;

    /**
     * For fields that can be selected, values can represent interpretation of the values,
     * for instance ['F'=>'Female', 'M'=>'Male'];.
     *
     * @var array|null
     */
    public $values = null;

    /**
     * If value of this field can be described by a model, this property
     * will contain reference to that model.
     */
    public $reference = null;

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
     * will always return meaningful label (even if caption is null). Set
     * this property to any string.
     *
     * @var string
     */
    public $caption = null;

    /**
     * Array with UI flags like editable, visible and hidden and settings
     * like caption.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Array with Persistence settings like format, timezone etc.
     * It's job of Persistence to take these settings into account if needed.
     *
     * @var array
     */
    public $persistence = [];

    /**
     * Mandatory field must not be null. The value must be set, even if
     * it's an empty value.
     *
     * Think about this property as "NOT NULL" property.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Required field must have non-empty value. A null value is considered empty too.
     *
     * Think about this property as !empty($value) property with some exceptions.
     *
     * This property takes precedence over $mandatory property.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $required = false;

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
     * Validate and normalize value.
     *
     * Depending on the type of a current field, this will perform
     * some normalization for strict types. This method must also make
     * sure that $f->required is respected when setting the value, e.g.
     * you can't set value to '' if type=string and required=true.
     *
     * @param mixed $value
     *
     * @throws ValidationException
     *
     * @return mixed
     */
    public function normalize($value)
    {
        // SQL fields are allowed to have expressions inside of them.
        if ($value instanceof Expression ||
            $value instanceof Expressionable) {
            return $value;
        }

        // NULL value is always fine if it is allowed
        if ($value === null) {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be null']);
            }

            return;
        }

        $f = $this;

        // only string type fields can use empty string as legit value, for all
        // other field types empty value is the same as no-value, nothing or null
        if ($f->type && $f->type != 'string' && $value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be empty']);
            }

            return;
        }

        // validate scalar values
        if (in_array($f->type, ['string', 'text', 'integer', 'money', 'float']) && !is_scalar($value)) {
            throw new ValidationException([$this->name => 'Must use scalar value']);
        }

        // normalize
        // @TODO remove this block in future - it's useless
        switch ($f->type) {
        case null: // loose comparison, but is OK here
            // NOTE - this is not always the same as type=string. Need to review what else it can be and how type=null is used at all
            if ($this->required && empty($value)) {
                throw new ValidationException([$this->name => 'Must not be empty']);
            }
            break;
        case 'string':
            throw new Exception(['Use Field\ShortText for type=string', 'this'=>$this]);
        case 'text':
            throw new Exception(['Use Field\Text for type=text', 'this'=>$this]);
        case 'integer':
            throw new Exception(['Use Field\Integer for type=integer', 'this'=>$this]);
        case 'float':
            throw new Exception(['Use Field\Numeric for type=float', 'this'=>$this]);
        case 'money':
            throw new Exception(['Use Field\Money for type=money', 'this'=>$this]);
        case 'boolean':
            throw new Exception(['Use Field\Boolean for type=boolean', 'this'=>$this]);
        case 'date':
            throw new Exception(['Use Field\Date for type=date', 'this'=>$this]);
        case 'datetime':
            throw new Exception(['Use Field\DateTime for type=datetime', 'this'=>$this]);
        case 'time':
            throw new Exception(['Use Field\Time for type=time', 'this'=>$this]);
        case 'array':
            throw new Exception(['Use Field\Array_ for type=array', 'this'=>$this]);
        case 'object':
            throw new Exception(['Use Field\Object_ for type=object', 'this'=>$this]);
        }

        return $value;
    }

    /**
     * Return array of seed properties of this Field object.
     *
     * @param array $properties Properties to return, by default will return all properties set.
     *
     * @return array
     */
    public function getSeed(array $properties = []) : array
    {
        $seed = [];

        // [key => default_value]
        $properties = $properties ?: [
            'default'       => null,
            'type'          => null,
            'enum'          => null,
            'values'        => null,
            'reference'     => null,
            'actual'        => null,
            'join'          => null,
            'system'        => false,
            'never_persist' => false,
            'never_save'    => false,
            'read_only'     => false,
            'caption'       => null,
            'ui'            => [],
            'persistence'   => [],
            'mandatory'     => false,
            'required'      => false,
            'typecast'      => null,
            'serialize'     => null,
        ];

        foreach ($properties as $k=>$v) {
            if ($this->{$k} !== $v) {
                $seed[$k] = $this->{$k};
            }
        }

        return $seed;
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     *
     * @return string|mixed
     */
    public function toString($value = null)
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        return $this->type === null ? $v : (string) $v;
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
        $this->owner->set($this->short_name, $value);

        return $this;
    }

    /**
     * This method can be extended. See Model::compare for
     * use examples.
     *
     * @param mixed $value
     *
     * @return bool
     */
    public function compare($value) : bool
    {
        return $this->owner[$this->short_name] == $value;
    }

    /**
     * Should this field use alias?
     *
     * @return bool
     */
    public function useAlias()
    {
        return isset($this->actual);
    }

    // }}}

    // {{{ Handy methods used by UI and in other places

    /**
     * Returns if field should be editable in UI.
     *
     * @return bool
     */
    public function isEditable() : bool
    {
        return $this->ui['editable'] ?? (($this->read_only || $this->never_persist) ? false : !$this->system);
    }

    /**
     * Returns if field should be visible in UI.
     *
     * @return bool
     */
    public function isVisible() : bool
    {
        return $this->ui['visible'] ?? !$this->system;
    }

    /**
     * Returns if field should be hidden in UI.
     *
     * @return bool
     */
    public function isHidden() : bool
    {
        return $this->ui['hidden'] ?? false;
    }

    /**
     * Returns true if field allows NULL values.
     *
     * @return bool
     */
    public function canBeNull() : bool
    {
        return $this->mandatory === false;
    }

    /**
     * Returns true if field allows EMPTY values like empty string and NULL.
     *
     * @return bool
     */
    public function canBeEmpty() : bool
    {
        return $this->mandatory === false && $this->required === false;
    }

    /**
     * Returns field caption for use in UI.
     *
     * @return string
     */
    public function getCaption() : string
    {
        return $this->caption ?? $this->ui['caption'] ?? $this->readableCaption($this->short_name);
    }

    // }}}

    /**
     * When field is used as expression, this method will be called.
     * Universal way to convert ourselves to expression. Off-load implementation into persistence.
     *
     * @param Expression $expression
     *
     * @throws Exception
     *
     * @return Expression
     */
    public function getDSQLExpression($expression)
    {
        if (!$this->owner->persistence || !$this->owner->persistence instanceof Persistence\SQL) {
            throw new Exception([
                'Field must have SQL persistence if it is used as part of expression',
                'persistence'=> $this->owner->persistence ?? null,
            ]);
        }

        return $this->owner->persistence->getFieldSQLExpression($this, $expression);
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     *
     * @return array
     */
    public function __debugInfo() : array
    {
        $arr = [
            'short_name' => $this->short_name,
            'value'      => $this->get(),
        ];

        foreach ([
            'type', 'system', 'never_persist', 'never_save', 'read_only', 'ui', 'persistence', 'join',
        ] as $key) {
            if (isset($this->$key)) {
                $arr[$key] = $this->$key;
            }
        }

        return $arr;
    }

    // }}}
}
