<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\core\DIContainerTrait;
use atk4\core\InitializerTrait;
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
    use InitializerTrait {
        init as _init;
    }

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
     * Value can be array ['save' => $typecast_save_callback, 'load' => $typecast_load_callback].
     *
     * @var null|bool|array
     */
    public $typecast = null;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array ['encode' => $encode_callback, 'decode' => $decode_callback].
     *
     * @var null|bool|array
     */
    public $serialize = null;

    protected static $seedProperties = [
        'default',
        'type',
        'enum',
        'values',
        'reference',
        'actual',
        'join',
        'system',
        'never_persist',
        'never_save',
        'read_only',
        'caption',
        'ui',
        'persistence',
        'mandatory',
        'required',
        'typecast',
        'serialize',
    ];

    /**
     * Map field type to seed
     * List can be updated or extended using the Field::register method.
     *
     * @var array
     */
    protected static $registry = [
        'boolean'  => Field\Boolean::class,
        'float'    => Field\Numeric::class,
        'integer'  => Field\Integer::class,
        'int'      => Field\Integer::class,
        'money'    => Field\Money::class,
        'text'     => Field\Text::class,
        'string'   => Field\Line::class,
        'datetime' => Field\DateTime::class,
        'date'     => Field\Date::class,
        'time'     => Field\Time::class,
        'array'    => Field\Array_::class,
        'object'   => Field\Object_::class,
    ];

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
     * Initialization.
     */
    public function init()
    {
        $this->_init();
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
        // NULL value is always fine if it is allowed
        if ($value === null || $value === '') {
            if ($this->required) {
                throw new ValidationException([$this->name => 'Must not be null or empty']);
            }
        }

        return $value;
    }

    public static function isExpression($value)
    {
        return $value instanceof Expression || $value instanceof Expressionable;
    }

    /**
     * Return array of seed properties of this Field object.
     *
     * @param array $properties Properties to return, by default will return all properties set.
     *
     * @return array
     */
    public function getSeed(array $defaults = []): array
    {
        if (!$defaults) {
            $seedProperties = static::$seedProperties;
            foreach (class_parents($this) as $parent) {
                $seedProperties = array_merge($parent::$seedProperties, $seedProperties);
            }

            $defaults = array_intersect_key(get_class_vars(static::class), array_flip($seedProperties));
        }

        $seed = [];
        foreach ($defaults as $k=>$v) {
            if ($this->{$k} !== $v) {
                $seed[$k] = $this->{$k};
            }
        }

        return $seed;
    }

    /**
     * Resolve field type to seed from Field::$registry.
     *
     * @param string $type
     */
    public static function resolve($type)
    {
        return self::$registry[$type] ?? null;
    }

    /**
     * Register custom field type to be resolved.
     *
     * @param string|array      $type
     * @param string|array|null $seed
     */
    public static function register($type, $seed = null)
    {
        if (is_array($types = $type)) {
            foreach ($types as $type => $seed) {
                self::register($type, $seed);
            }
        }

        self::$registry[$type] = $seed;
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
    public function compare($value): bool
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
    public function isEditable(): bool
    {
        return $this->ui['editable'] ?? (($this->read_only || $this->never_persist) ? false : !$this->system);
    }

    /**
     * Returns if field should be visible in UI.
     *
     * @return bool
     */
    public function isVisible(): bool
    {
        return $this->ui['visible'] ?? !$this->system;
    }

    /**
     * Returns if field should be hidden in UI.
     *
     * @return bool
     */
    public function isHidden(): bool
    {
        return $this->ui['hidden'] ?? false;
    }

    /**
     * Returns true if field allows NULL values.
     *
     * @return bool
     */
    public function canBeNull(): bool
    {
        return $this->mandatory === false;
    }

    /**
     * Returns true if field allows EMPTY values like empty string and NULL.
     *
     * @return bool
     */
    public function canBeEmpty(): bool
    {
        return $this->mandatory === false && $this->required === false;
    }

    /**
     * Returns field caption for use in UI.
     *
     * @return string
     */
    public function getCaption(): string
    {
        return $this->caption ?? $this->ui['caption'] ?? $this->readableCaption($this->short_name);
    }

    /**
     * Returns typecasting callback if defined.
     *
     * Typecasting can be defined as (in order of precedence)
     *
     * * affects all typecasting for the field
     * $user->addField('dob', ['Date', 'typecast'=>[$encode_fx, $decode_fx]]);
     *
     * * affects typecasting for specific persistence class
     * $user->addField('dob', ['Date', 'persistence'=>['atk4\data\Persistence\SQL'=>['typecast'=>[$encode_fx, $decode_fx]]]]);
     *
     * * affects typecasting for all persistences
     * $user->addField('dob', ['Date', 'persistence'=>['typecast'=>[$encode_fx, $decode_fx]]]);
     *
     * * default typecasting (if none of above set) will be used for all fields of the class defined in field methods
     * typecastSave / typecastLoad based on the $mode
     *
     * @param string $mode - load|save
     *
     * @return callable|false
     */
    public function getTypecaster($mode)
    {
        // map for backward compatibility with definition
        // [typecast_save_callback, typecast_load_callback]
        $map = [
            'save' => 0,
            'load' => 1,
        ];

        $persistence = $this->getPersistence();

        // persistence specific typecast
        $specific = $persistence ? ($this->persistence[get_class($persistence)]['typecast'] ?? null) : null;

        // get the typecast definition to be applied
        // field specific or persistence specific or persistence general
        $typecast = $this->typecast ?? $specific ?? $this->persistence['typecast'] ?? [];

        // default typecaster is method in the field named typecastSave or typecastLoad if such method exists
        $default = method_exists($this, 'typecast'.ucfirst($mode)) ? [$this, 'typecast'.ucfirst($mode)] : false;

        $fx = $typecast[$mode] ?? $typecast[$map[$mode]] ?? $default;

        return is_callable($fx) ? $fx : false;
    }

    /**
     * Returns serialize callback if defined.
     *
     * @param string $mode - encode|decode
     *
     * @return callable|false
     */
    public function getSerializer($mode)
    {
        // map for backward compatibility with definition
        // [encode_callback, decode_callback]
        $map = [
            'encode' => 0,
            'decode' => 1,
        ];

        $fx = $this->serialize[$mode] ?? $this->serialize[$map[$mode]] ?? false;

        return is_callable($fx) ? $fx : false;
    }

    public function getPersistence()
    {
        return $this->owner ? $this->owner->persistence : null;
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
    public function __debugInfo(): array
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
