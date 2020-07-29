<?php

declare(strict_types=1);

namespace atk4\data;

use atk4\core\DiContainerTrait;
use atk4\core\ReadableCaptionTrait;
use atk4\core\TrackableTrait;
use atk4\dsql\Expression;
use atk4\dsql\Expressionable;

/**
 * Class description?
 *
 * @property Model $owner
 */
class Field implements Expressionable
{
    use TrackableTrait;
    use DiContainerTrait;
    use ReadableCaptionTrait;

    // {{{ Properties

    /**
     * Default value of field.
     *
     * @var mixed
     */
    public $default;

    /**
     * Field type.
     *
     * Values are: 'string', 'text', 'boolean', 'integer', 'money', 'float',
     *             'date', 'datetime', 'time', 'array', 'object'.
     * Can also be set to unspecified type for your own custom handling.
     *
     * @var string
     */
    public $type;

    /**
     * For several types enum can provide list of available options. ['blue', 'red'].
     *
     * @var array|null
     */
    public $enum;

    /**
     * For fields that can be selected, values can represent interpretation of the values,
     * for instance ['F'=>'Female', 'M'=>'Male'];.
     *
     * @var array|null
     */
    public $values;

    /**
     * If value of this field can be described by a model, this property
     * will contain reference to that model.
     *
     * It's used more in atk4/ui repository. See there.
     *
     * @var Reference|null
     */
    public $reference;

    /**
     * Actual field name.
     *
     * @var string|null
     */
    public $actual;

    /**
     * Join object.
     *
     * @var Join|null
     */
    public $join;

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
    public $caption;

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
     * @var bool|array|null
     */
    public $typecast;

    /**
     * Should we use serialization when saving/loading data to/from persistence.
     *
     * Value can be array [$encode_callback, $decode_callback].
     *
     * @var bool|array|null
     */
    public $serialize;

    /**
     * Persisting format for type = 'date', 'datetime', 'time' fields.
     *
     * For example, for date it can be 'Y-m-d', for datetime - 'Y-m-d H:i:s.u' etc.
     *
     * @var string
     */
    public $persist_format;

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
     * For example, 'DateTime', 'Carbon\Carbon' etc.
     *
     * @var string
     */
    public $dateTimeClass = \DateTime::class;

    /**
     * Timezone class used for type = 'data', 'datetime', 'time' fields.
     *
     * For example, 'DateTimeZone', 'Carbon\CarbonTimeZone' etc.
     *
     * @var string
     */
    public $dateTimeZoneClass = \DateTimeZone::class;

    // }}}

    // {{{ Core functionality

    /**
     * Constructor. You can pass field properties as array.
     *
     * @param array $defaults
     */
    public function __construct($defaults = [])
    {
        if (!is_array($defaults)) {
            throw (new Exception('Field requires array for defaults'))
                ->addMoreInfo('arg', $defaults);
        }
        foreach ($defaults as $key => $val) {
            if (is_array($val)) {
                $this->{$key} = array_merge(isset($this->{$key}) && is_array($this->{$key}) ? $this->{$key} : [], $val);
            } else {
                $this->{$key} = $val;
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
     * @return mixed
     */
    public function normalize($value)
    {
        try {
            if (!$this->owner->strict_types) {
                return $value;
            }

            if ($value === null) {
                if ($this->required/* known bug, see https://github.com/atk4/data/issues/575, fix in https://github.com/atk4/data/issues/576 || $this->mandatory*/) {
                    throw new ValidationException([$this->name => 'Must not be null']);
                }

                return;
            }

            $f = $this;

            // only string type fields can use empty string as legit value, for all
            // other field types empty value is the same as no-value, nothing or null
            if ($f->type && $f->type !== 'string' && $value === '') {
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be empty']);
                }

                return;
            }

            // validate scalar values
            if (in_array($f->type, ['string', 'text', 'integer', 'money', 'float'], true)) {
                if (!is_scalar($value)) {
                    throw new ValidationException([$this->name => 'Must use scalar value']);
                }

                $value = (string) $value;
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
                $value = round((float) $value, 4);
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be a zero']);
                }

                break;
            case 'boolean':
                throw (new Exception('Use Field\Boolean for type=boolean'))
                    ->addMoreInfo('this', $this);
            case 'date':
            case 'datetime':
            case 'time':
                // we allow http://php.net/manual/en/datetime.formats.relative.php
                $class = $f->dateTimeClass ?? \DateTime::class;

                if (is_numeric($value)) {
                    $value = new $class('@' . $value);
                } elseif (is_string($value)) {
                    $value = new $class($value);
                } elseif (!$value instanceof $class) {
                    if ($value instanceof \DateTimeInterface) {
                        $value = new $class($value->format('Y-m-d H:i:s.u'), $value->getTimezone());
                    } else {
                        if (is_object($value)) {
                            throw new ValidationException(['must be a ' . $f->type, 'class' => $class, 'value class' => get_class($value)]);
                        }

                        throw new ValidationException(['must be a ' . $f->type, 'class' => $class, 'value type' => gettype($value)]);
                    }
                }

                if ($f->type === 'date' && $value->format('H:i:s.u') !== '00:00:00.000000') {
                    // remove time portion from date type value
                    $value = (clone $value)->setTime(0, 0, 0);
                }
                if ($f->type === 'time' && $value->format('Y-m-d') !== '1970-01-01') {
                    // remove date portion from date type value
                    // need 1970 in place of 0 - DB
                    $value = (clone $value)->setDate(1970, 1, 1);
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
                throw (new Exception('Use of obsolete field type abbreviation. Use "integer", "string", "boolean" etc.'))
                    ->addMoreInfo('type', $f->type);

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
     */
    public function toString($value = null): string
    {
        $v = ($value === null ? $this->get() : $this->normalize($value));

        try {
            switch ($this->type) {
                case 'boolean':
                    throw (new Exception('Use Field\Boolean for type=boolean'))
                        ->addMoreInfo('this', $this);
                case 'date':
                case 'datetime':
                case 'time':
                    if ($v instanceof \DateTimeInterface) {
                        $dateFormat = 'Y-m-d';
                        $timeFormat = 'H:i:s' . ($v->format('u') > 0 ? '.u' : ''); // add microseconds if presented
                        if ($this->type === 'date') {
                            $format = $dateFormat;
                        } elseif ($this->type === 'time') {
                            $format = $timeFormat;
                        } else {
                            $format = $dateFormat . '\T' . $timeFormat . 'P'; // ISO 8601 format 2004-02-12T15:19:21+00:00
                        }

                        return $v->format($format);
                    }

                    return (string) $v;
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
        return $this->owner->get($this->short_name);
    }

    /**
     * Sets field value.
     *
     * @param mixed $value
     */
    public function set($value): self
    {
        $this->owner->set($this->short_name, $value);

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     */
    public function setNull(): self
    {
        $this->owner->setNull($this->short_name);

        return $this;
    }

    /**
     * This method can be extended. See Model::compare for use examples.
     *
     * @param mixed $value
     */
    public function compare($value): bool
    {
        return $this->get() == $value;
    }

    /**
     * Should this field use alias?
     */
    public function useAlias(): bool
    {
        return isset($this->actual);
    }

    // }}}

    // {{{ Handy methods used by UI

    /**
     * Returns if field should be editable in UI.
     */
    public function isEditable(): bool
    {
        return $this->ui['editable'] ?? !$this->read_only && !$this->never_persist && !$this->system;
    }

    /**
     * Returns if field should be visible in UI.
     */
    public function isVisible(): bool
    {
        return $this->ui['visible'] ?? !$this->system;
    }

    /**
     * Returns if field should be hidden in UI.
     */
    public function isHidden(): bool
    {
        return $this->ui['hidden'] ?? false;
    }

    /**
     * Returns field caption for use in UI.
     */
    public function getCaption(): string
    {
        $caption = $this->caption ?? $this->ui['caption'] ?? $this->readableCaption($this->short_name);

        return $caption instanceof \Closure ? $caption($this) : $caption;
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
    public function getDsqlExpression($expression)
    {
        if (!$this->owner->persistence || !$this->owner->persistence instanceof Persistence\Sql) {
            throw (new Exception('Field must have SQL persistence if it is used as part of expression'))
                ->addMoreInfo('persistence', $this->owner->persistence ?? null);
        }

        return $this->owner->persistence->getFieldSqlExpression($this, $expression);
    }

    // {{{ Debug Methods

    /**
     * Returns array with useful debug info for var_dump.
     */
    public function __debugInfo(): array
    {
        $arr = [
            'short_name' => $this->short_name,
            'value' => $this->get(),
        ];

        foreach ([
            'type', 'system', 'never_persist', 'never_save', 'read_only', 'ui', 'join',
        ] as $key) {
            if (isset($this->{$key})) {
                $arr[$key] = $this->{$key};
            }
        }

        return $arr;
    }

    // }}}
}
