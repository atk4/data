<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\ReadableCaptionTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Model\Scope;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Types\Type;

/**
 * @method Model getOwner()
 */
class Field implements Expressionable
{
    use DiContainerTrait;
    use Model\FieldPropertiesTrait;
    use Model\JoinLinkTrait;
    use ReadableCaptionTrait;
    use TrackableTrait;

    // {{{ Core functionality

    /**
     * Constructor. You can pass field properties as array.
     */
    public function __construct(array $defaults = [])
    {
        foreach ($defaults as $key => $val) {
            if (is_array($val)) {
                $this->{$key} = array_replace_recursive(is_array($this->{$key} ?? null) ? $this->{$key} : [], $val);
            } else {
                $this->{$key} = $val;
            }
        }
    }

    public function getTypeObject(): Type
    {
        if ($this->type === 'array') { // remove in 2022-mar
            throw new Exception('Atk4 "array" type is no longer supported, originally, it serialized value to JSON, to keep this behaviour, use "json" type');
        }

        return Type::getType($this->type ?? 'string');
    }

    protected function onHookShortToOwner(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->short_name; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamicShort(
            $spot,
            static function (Model $owner) use ($name) {
                return $owner->getField($name);
            },
            $fx,
            $args,
            $priority
        );
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
            if (!$this->getOwner()->strict_types || $this->getOwner()->hook(Model::HOOK_NORMALIZE, [$this, $value]) === false) {
                return $value;
            }

            if ($value === null) {
                if ($this->required/* known bug, see https://github.com/atk4/data/issues/575, fix in https://github.com/atk4/data/issues/576 || $this->mandatory*/) {
                    throw new ValidationException([$this->name => 'Must not be null'], $this->getOwner());
                }

                return;
            }

            $f = $this;

//            $platform = $this->getDatabasePlatform();
            $type = $this->getTypeObject();
//            TODO - breaking tests$value = $type->convertToPHPValue($type->convertToDatabaseValue($value, $platform), $platform);

            // only string type fields can use empty string as legit value, for all
            // other field types empty value is the same as no-value, nothing or null
            if ($f->type && $f->type !== 'string' && $value === '') {
                if ($this->required && empty($value)) {
                    throw new ValidationException([$this->name => 'Must not be empty'], $this->getOwner());
                }

                return;
            }

            // validate scalar values
            if (in_array($f->type, ['string', 'text', 'integer', 'money', 'float'], true)) {
                if (!is_scalar($value)) {
                    throw new ValidationException([$this->name => 'Must use scalar value'], $this->getOwner());
                }

                $value = (string) $value;
            }

            // normalize
            switch ($f->type) {
                case null: // loose comparison, but is OK here
                    if ($this->required && empty($value)) {
                        throw new ValidationException([$this->name => 'Must not be empty'], $this->getOwner());
                    }

                    break;
                case 'string':
                    // remove all line-ends and trim
                    $value = trim(str_replace(["\r", "\n"], '', $value));
                    if ($this->required && empty($value)) {
                        throw new ValidationException([$this->name => 'Must not be empty'], $this->getOwner());
                    }

                    break;
                case 'text':
                    // normalize line-ends to LF and trim
                    $value = trim(str_replace(["\r\n", "\r"], "\n", $value));
                    if ($this->required && empty($value)) {
                        throw new ValidationException([$this->name => 'Must not be empty'], $this->getOwner());
                    }

                    break;
                case 'integer':
                    // we clear out thousand separator, but will change to
                    // http://php.net/manual/en/numberformatter.parse.php
                    // in the future with the introduction of locale
                    $value = trim(str_replace(["\r", "\n"], '', $value));
                    $value = preg_replace('/[,`\']/', '', $value);
                    if (!is_numeric($value)) {
                        throw new ValidationException([$this->name => 'Must be numeric'], $this->getOwner());
                    }
                    $value = (int) $value;
                    if ($this->required && empty($value)) {
                        throw new ValidationException([$this->name => 'Must not be a zero'], $this->getOwner());
                    }

                    break;
                case 'float':
                    $value = trim(str_replace(["\r", "\n"], '', $value));
                    $value = preg_replace('/[,`\']/', '', $value);
                    if (!is_numeric($value)) {
                        throw new ValidationException([$this->name => 'Must be numeric'], $this->getOwner());
                    }
                    $value = (float) $value;
                    if ($this->required && empty($value)) {
                        throw new ValidationException([$this->name => 'Must not be a zero'], $this->getOwner());
                    }

                    break;
                case 'money':
                    $value = trim(str_replace(["\r", "\n"], '', $value));
                    $value = preg_replace('/[,`\']/', '', $value);
                    if (!is_numeric($value)) {
                        throw new ValidationException([$this->name => 'Must be numeric'], $this->getOwner());
                    }
                    $value = round((float) $value, 4);
                    if ($this->required && empty($value)) {
                        throw new ValidationException([$this->name => 'Must not be a zero'], $this->getOwner());
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
                                throw new ValidationException(['must be a ' . $f->type, 'class' => $class, 'value class' => get_class($value)], $this->getOwner());
                            }

                            throw new ValidationException(['must be a ' . $f->type, 'class' => $class, 'value type' => gettype($value)], $this->getOwner());
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
                case 'json':
                    if (is_string($value) && $f->issetOwner() && $f->getOwner()->persistence) {
                        $value = $f->getOwner()->persistence->typecastLoadField($f, $value);
                    }

                    if (!is_array($value)) {
                        throw new ValidationException([$this->name => 'Must be an array'], $this->getOwner());
                    }

                    break;
                case 'object':
                   if (is_string($value) && $f->issetOwner() && $f->getOwner()->persistence) {
                       $value = $f->getOwner()->persistence->typecastLoadField($f, $value);
                   }

                    if (!is_object($value)) {
                        throw new ValidationException([$this->name => 'Must be an object'], $this->getOwner());
                    }

                    break;
                case 'int':
                case 'str':
                case 'bool':
                    throw (new Exception('Use of obsolete field type abbreviation. Use "integer", "string", "boolean" etc.'))
                        ->addMoreInfo('type', $f->type);
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
                case 'json':
                    return json_encode($v);
                case 'object':
                    return serialize($v);
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
        return $this->getOwner()->get($this->short_name);
    }

    /**
     * Sets field value.
     *
     * @param mixed $value
     */
    public function set($value): self
    {
        $this->getOwner()->set($this->short_name, $value);

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     */
    public function setNull(): self
    {
        $this->getOwner()->setNull($this->short_name);

        return $this;
    }

    private function getDatabasePlatform(): Platforms\AbstractPlatform
    {
        return $this->getOwner()->persistence !== null
            ? $this->getOwner()->persistence->getDatabasePlatform()
            : new Persistence\GenericPlatform();
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function typecastSaveField($value, bool $allowDummyPersistence = false)
    {
        $persistence = $this->getOwner()->persistence;
        if ($persistence === null) {
            if ($allowDummyPersistence) {
                $persistence = (new \ReflectionClass(Persistence\Sql::class))->newInstanceWithoutConstructor();
            } else {
                $this->getOwner()->checkPersistence();
            }
        }

        return $persistence->typecastSaveRow($this->getOwner(), [$this->short_name => $value])[$this->getPersistenceName()];
    }

    /**
     * @param mixed|void $value
     */
    public function getUnmanagedValue($value = null): ?string
    {
        if (func_num_args() === 0) {
            $value = $this->get();
        }

        $unmanagedValue = $this->getTypeObject()
            ->convertToDatabaseValue($value, $this->getDatabasePlatform());

        if (is_int($unmanagedValue) || is_float($unmanagedValue)) {
            return (string) $unmanagedValue;
        } elseif (is_bool($unmanagedValue)) {
            return $unmanagedValue ? '1' : '0';
        }

        return $unmanagedValue; // throw a type error if not null nor string
    }

    public function setUnmanagedValue(string $unmanagedValue = null): self
    {
        $value = $this->getTypeObject()
            ->convertToPHPValue($unmanagedValue, $this->getDatabasePlatform());

        return $this->set($value);
    }

    /**
     * Compare new value of the field with existing one without retrieving.
     * In the trivial case it's same as ($value == $model->get($name)) but this method can be used for:
     *  - comparing values that can't be received - passwords, encrypted data
     *  - comparing images
     *  - if get() is expensive (e.g. retrieve object).
     *
     * @param mixed      $value
     * @param mixed|void $value2
     */
    public function compare($value, $value2 = null): bool
    {
        if (func_num_args() === 1) {
            $value2 = $this->get();
        }

        try {
            return $this->getUnmanagedValue($value) === $this->getUnmanagedValue($value2);
        } catch (\TypeError $e) { // like https://github.com/atk4/data/pull/894, TODO, then no try/catch should be needed
            if ($e->getMessage() === 'Return value of Atk4\Data\Field::getUnmanagedValue() must be of the type string or null, object returned') {
                return serialize($value) === serialize($value2);
            }

            throw $e;
        }
    }

    public function getReference(): ?Reference
    {
        return $this->referenceLink !== null
            ? $this->getOwner()->getRef($this->referenceLink)
            : null;
    }

    public function getPersistenceName(): string
    {
        return $this->actual ?? $this->short_name;
    }

    /**
     * Should this field use alias?
     */
    public function useAlias(): bool
    {
        return isset($this->actual);
    }

    // }}}

    // {{{ Scope condition

    /**
     * Returns arguments to be used for query on this field based on the condition.
     *
     * @param string|null $operator one of Scope\Condition operators
     * @param mixed       $value    the condition value to be handled
     */
    public function getQueryArguments($operator, $value): array
    {
        $typecastField = $this;
        $allowArray = true;
        if (in_array($operator, [
            Scope\Condition::OPERATOR_LIKE,
            Scope\Condition::OPERATOR_NOT_LIKE,
            Scope\Condition::OPERATOR_REGEXP,
            Scope\Condition::OPERATOR_NOT_REGEXP,
        ], true)) {
            $typecastField = new self(['type' => 'string']);
            $typecastField->setOwner(new Model($this->getOwner()->persistence, ['table' => false]));
            $typecastField->short_name = $this->short_name;
            $allowArray = false;
        }

        return [
            $this,
            $operator,
            is_array($value) && $allowArray
                ? array_map(fn ($value) => $typecastField->typecastSaveField($value), $value)
                : $typecastField->typecastSaveField($value),
        ];
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
        return $this->caption ?? $this->ui['caption'] ?? $this->readableCaption(preg_replace('~^atk_fp_\w+?__~', '', $this->short_name));
    }

    // }}}

    /**
     * When field is used as expression, this method will be called.
     * Universal way to convert ourselves to expression. Off-load implementation into persistence.
     */
    public function getDsqlExpression(Expression $expression): Expression
    {
        if (!$this->getOwner()->persistence || !$this->getOwner()->persistence instanceof Persistence\Sql) {
            throw (new Exception('Field must have SQL persistence if it is used as part of expression'))
                ->addMoreInfo('persistence', $this->getOwner()->persistence ?? null);
        }

        return $this->getOwner()->persistence->getFieldSqlExpression($this, $expression);
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
            'type', 'system', 'never_persist', 'never_save', 'read_only', 'ui', 'joinName',
        ] as $key) {
            if (isset($this->{$key})) {
                $arr[$key] = $this->{$key};
            }
        }

        return $arr;
    }

    // }}}
}
