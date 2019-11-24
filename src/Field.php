<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

use atk4\core\DIContainerTrait;
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
     * Values are: 'string', 'text', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
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
     * Array with UI flags like editable, visible and hidden.
     *
     * @var array
     */
    public $ui = [];

    /**
     * Mandatory field must not be null. The value must be set, even if
     * it's an empty value.
     *
     * Can contain error message for UI.
     *
     * @var bool|string
     */
    public $mandatory = false;

    /**
     * Required field must have non-empty value. A null value is considered empty too.
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
        try {
            if (!$this->owner->strict_types) {
                return $value;
            }

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
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be empty']);
                }

                return;
            }

            // validate scalar values
            if (in_array($f->type, ['string', 'text', 'integer', 'money', 'float']) && !is_scalar($value)) {
                throw new ValidationException([$this->name => 'Must use scalar value']);
            }

            // normalize
            switch ($f->type) {
            case null: // loose comparison, but is OK here
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be empty']);
                }
                break;
            case 'string':
                // remove all line-ends and trim
                $value = trim(str_replace(["\r", "\n"], '', $value));
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be empty']);
                }
                break;
            case 'text':
                // normalize line-ends to LF and trim
                $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be empty']);
                }
                break;
            case 'integer':
                // we clear out thousand separator, but will change to
                // http://php.net/manual/en/numberformatter.parse.php
                // in the future with the introduction of locale
                $value = trim(str_replace(["\r", "\n"], '', $value));
                $value = preg_replace('/[,`\']/', '', $value);
                if (!is_numeric($value)) {
                    throw new ValidationException([$this->name => 'Must be numeric']);
                }
                $value = (int) $value;
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be a zero']);
                }
                break;
            case 'float':
                $value = trim(str_replace(["\r", "\n"], '', $value));
                $value = preg_replace('/[,`\']/', '', $value);
                if (!is_numeric($value)) {
                    throw new ValidationException([$this->name => 'Must be numeric']);
                }
                $value = (float) $value;
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be a zero']);
                }
                break;
            case 'money':
                $value = trim(str_replace(["\r", "\n"], '', $value));
                $value = preg_replace('/[,`\']/', '', $value);
                if (!is_numeric($value)) {
                    throw new ValidationException([$this->name => 'Must be numeric']);
                }
                $value = round($value, 4);
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be a zero']);
                }
                break;
            case 'boolean':
                throw new Exception(['Use Field\Boolean for type=boolean', 'this'=>$this]);
            case 'date':
            case 'datetime':
            case 'time':
                // we allow http://php.net/manual/en/datetime.formats.relative.php
                $class = isset($f->dateTimeClass) ? $f->dateTimeClass : 'DateTime';

                if (is_numeric($value)) {
                    $value = new $class('@'.$value);
                } elseif (is_string($value)) {
                    $value = new $class($value);
                } elseif (!$value instanceof $class) {
                    if (is_object($value)) {
                        throw new ValidationException(['must be a '.$f->type, 'class' => $class, 'value class' => get_class($value)]);
                    }

                    throw new ValidationException(['must be a '.$f->type, 'class' => $class, 'value type' => gettype($value)]);
                }

                if ($f->type == 'date') {
                    // remove time portion from date type value
                    $value->setTime(0, 0, 0);
                }
                if ($f->type == 'time') {
                    // remove date portion from date type value
                    // need 1970 in place of 0 - DB
                    $value->setDate(1970, 1, 1);
                }

                break;
            case 'array':
                if (is_string($value) && $f->owner && $f->owner->persistence) {
                    $value = $f->owner->persistence->jsonDecode($f, $value, true);
                }

                if (!is_array($value)) {
                    throw new ValidationException([$this->name => 'Must be an array']);
                }
                break;
            case 'object':
               if (is_string($value) && $f->owner && $f->owner->persistence) {
                   $value = $f->owner->persistence->jsonDecode($f, $value, false);
               }

                if (!is_object($value)) {
                    throw new ValidationException([$this->name => 'Must be an object']);
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
        } catch (Exception $e) {
            $e->addMoreInfo('field', $this);

            throw $e;
        }
    }

    /**
     * Casts field value to string.
     *
     * @param mixed $value Optional value
     *
     * @return string
     */
    public function toString($value = null)
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        try {
            switch ($this->type) {
                case null: // loose comparison, but is OK here
                    return $v;
                case 'boolean':
                    throw new Exception(['Use Field\Boolean for type=boolean', 'this'=>$this]);
                case 'date':
                    return $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : (string) $v;
                case 'datetime':
                    return $v instanceof \DateTimeInterface ? $v->format('c') : (string) $v; // ISO 8601 format 2004-02-12T15:19:21+00:00
                case 'time':
                    return $v instanceof \DateTimeInterface ? $v->format('H:i:s') : (string) $v;
                case 'array':
                    return json_encode($v); // todo use Persistence->jsonEncode() instead
                case 'object':
                    return json_encode($v); // todo use Persistence->jsonEncode() instead
                default:
                    return (string) $v;
            }
        } catch (Exception $e) {
            $e->addMoreInfo('field', $this);

            throw $e;
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
    public function compare($value)
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

    // {{{ Handy methods used by UI

    /**
     * Returns if field should be editable in UI.
     *
     * @return bool
     */
    public function isEditable()
    {
        return isset($this->ui['editable']) ? $this->ui['editable']
                : (($this->read_only || $this->never_persist) ? false
                    : !$this->system);
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

    /**
     * Returns field caption for use in UI.
     *
     * @return string
     */
    public function getCaption()
    {
        return $this->caption ?: (isset($this->ui['caption']) ? $this->ui['caption'] :
            ucwords(str_replace('_', ' ', $this->short_name)));
    }

    // }}}

    /**
     * When field is used as expression, this method will be called.
     * Universal way to convert ourselves to expression. Off-load implementation into persistence.
     *
     * @param Expression $expression
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
