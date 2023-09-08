<?php

declare(strict_types=1);

namespace Atk4\Data;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\ReadableCaptionTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Model\Scope;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Doctrine\DBAL\Types\Type;

/**
 * @method Model getOwner()
 */
class Field implements Expressionable
{
    use DiContainerTrait {
        setDefaults as private _setDefaults;
    }
    use Model\FieldPropertiesTrait;
    use Model\JoinLinkTrait;
    use ReadableCaptionTrait;
    use TrackableTrait {
        setOwner as private _setOwner;
    }

    // {{{ Core functionality

    /**
     * @param array<string, mixed> $defaults
     */
    public function __construct(array $defaults = [])
    {
        $this->setDefaults($defaults);

        if (!(new \ReflectionProperty($this, 'type'))->isInitialized($this)) {
            $this->type = 'string';
        }
    }

    /**
     * @param Model $owner
     *
     * @return $this
     */
    public function setOwner(object $owner)
    {
        $owner->assertIsModel();

        return $this->_setOwner($owner);
    }

    /**
     * @param array<string, mixed> $properties
     */
    public function setDefaults(array $properties, bool $passively = false): self
    {
        $this->_setDefaults($properties, $passively);

        // assert type exists
        if (isset($properties['type'])) {
            if ($this->type === 'array') { // remove in v5.1
                throw new Exception('Atk4 "array" type is no longer supported, originally, it serialized value to JSON, to keep this behaviour, use "json" type');
            }

            Type::getType($this->type);
        }

        return $this;
    }

    /**
     * @template T of Model
     *
     * @param \Closure(T, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed $fx
     * @param array<int, mixed>                                                                        $args
     */
    protected function onHookToOwnerEntity(string $spot, \Closure $fx, array $args = [], int $priority = 5): int
    {
        $name = $this->shortName; // use static function to allow this object to be GCed

        return $this->getOwner()->onHookDynamic(
            $spot,
            static function (Model $entity) use ($name): self {
                $obj = $entity->getModel()->getField($name);
                $entity->assertIsEntity($obj->getOwner());

                return $obj;
            },
            $fx,
            $args,
            $priority
        );
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function normalizeUsingTypecast($value)
    {
        $persistence = $this->issetOwner() && $this->getOwner()->issetPersistence()
            ? $this->getOwner()->getPersistence()
            : new class() extends Persistence {
                public function __construct() {}
            };

        $persistenceSetSkipNormalizeFx = \Closure::bind(static function (bool $value) use ($persistence) {
            $persistence->typecastSaveSkipNormalize = $value;
        }, null, Persistence::class);

        $persistenceSetSkipNormalizeFx(true); // prevent recursion
        try {
            $value = $persistence->typecastSaveField($this, $value);
        } finally {
            $persistenceSetSkipNormalizeFx(false);
        }
        $value = $persistence->typecastLoadField($this, $value);

        return $value;
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
            if ($this->issetOwner() && $this->getOwner()->hook(Model::HOOK_NORMALIZE, [$this, $value]) === false) {
                return $value;
            }

            if (is_string($value)) {
                switch ($this->type) {
                    case 'string':
                        $value = trim(preg_replace('~\r?\n|\r|\s~', ' ', $value)); // remove all line-ends and trim

                        break;
                    case 'text':
                        $value = rtrim(preg_replace('~\r?\n|\r~', "\n", $value)); // normalize line-ends to LF and rtrim

                        break;
                    case 'boolean':
                    case 'integer':
                        $value = preg_replace('~\s+|[,`\']~', '', $value);

                        break;
                    case 'float':
                    case 'atk4_money':
                        $value = preg_replace('~\s+|[`\']|,(?=.*\.)~', '', $value);

                        break;
                }

                switch ($this->type) {
                    case 'boolean':
                    case 'integer':
                    case 'float':
                    case 'atk4_money':
                        if ($value === '') {
                            $value = null;
                        } elseif (!is_numeric($value)) {
                            throw new Exception('Must be numeric');
                        }

                        break;
                }
            } elseif ($value !== null) {
                switch ($this->type) {
                    case 'string':
                    case 'text':
                    case 'integer':
                    case 'float':
                    case 'atk4_money':
                        if (is_bool($value)) {
                            throw new Exception('Must not be boolean type');
                        } elseif (is_int($value)) {
                            $value = (string) $value;
                        } elseif (is_float($value)) {
                            $value = Expression::castFloatToString($value);
                        } else {
                            throw new Exception('Must be scalar');
                        }

                        break;
                }
            }

            $value = $this->normalizeUsingTypecast($value);

            if ($value === null) {
                if (!$this->nullable || $this->required) {
                    throw new Exception('Must not be null');
                }

                return null;
            }

            if ($value === '' && $this->required) {
                throw new Exception('Must not be empty');
            }

            switch ($this->type) {
                case 'string':
                case 'text':
                    if ($this->required && !$value) {
                        throw new Exception('Must not be empty');
                    }

                    break;
                case 'boolean':
                    if ($this->required && !$value) {
                        throw new Exception('Must be true');
                    }

                    break;
                case 'integer':
                case 'float':
                case 'atk4_money':
                    if ($this->required && !$value) {
                        throw new Exception('Must not be a zero');
                    }

                    break;
                case 'date':
                case 'datetime':
                case 'time':
                    if (!$value instanceof \DateTimeInterface) {
                        throw new Exception('Must be an instance of DateTimeInterface');
                    }

                    break;
                case 'json':
                    if (!is_array($value)) {
                        throw new Exception('Must be an array');
                    }

                    break;
                case 'object':
                    if (!is_object($value)) {
                        throw new Exception('Must be an object');
                    }

                    break;
            }

            if ($this->enum) {
                if ($value === '') {
                    $value = null;
                } elseif (!in_array($value, $this->enum, true)) {
                    throw new Exception('Value is not one of the allowed values: ' . implode(', ', $this->enum));
                }
            } elseif ($this->values) {
                if ($value === '') {
                    $value = null;
                } elseif ((!is_string($value) && !is_int($value)) || !array_key_exists($value, $this->values)) {
                    throw new Exception('Value is not one of the allowed values: ' . implode(', ', array_keys($this->values)));
                }
            }

            return $value;
        } catch (\Exception $e) {
            $messages = [];
            do {
                $messages[] = $e->getMessage();
            } while ($e = $e->getPrevious());

            throw (new ValidationException([$this->shortName => implode(': ', $messages)], $this->issetOwner() ? $this->getOwner() : null))
                ->addMoreInfo('field', $this);
        }
    }

    /**
     * Returns field value.
     *
     * @return mixed
     */
    final public function get(Model $entity)
    {
        $entity->assertIsEntity($this->getOwner());

        return $entity->get($this->shortName);
    }

    /**
     * Sets field value.
     *
     * @param mixed $value
     */
    final public function set(Model $entity, $value): self
    {
        $entity->assertIsEntity($this->getOwner());

        $entity->set($this->shortName, $value);

        return $this;
    }

    /**
     * Unset field value even if null value is not allowed.
     */
    final public function setNull(Model $entity): self
    {
        $entity->assertIsEntity($this->getOwner());

        $entity->setNull($this->shortName);

        return $this;
    }

    /**
     * @param mixed $value
     *
     * @return mixed
     */
    private function typecastSaveField($value, bool $allowGenericPersistence = false)
    {
        if (!$this->getOwner()->issetPersistence() && $allowGenericPersistence) {
            $persistence = new class() extends Persistence {
                public function __construct() {}
            };
        } else {
            $this->getOwner()->assertHasPersistence();
            $persistence = $this->getOwner()->getPersistence();
        }

        return $persistence->typecastSaveField($this, $value);
    }

    /**
     * @param mixed $value
     */
    private function getValueForCompare($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $res = $this->typecastSaveField($value, true);
        if (is_float($res)) {
            return Expression::castFloatToString($res);
        }

        return (string) $res;
    }

    /**
     * Compare new value of the field with existing one without retrieving.
     *
     * @param mixed $value
     * @param mixed $value2
     */
    public function compare($value, $value2): bool
    {
        if ($value === $value2) { // optimization only
            return true;
        }

        // TODO, see https://stackoverflow.com/questions/48382457/mysql-json-column-change-array-order-after-saving
        // at least MySQL sorts the JSON keys if stored natively
        return $this->getValueForCompare($value) === $this->getValueForCompare($value2);
    }

    public function hasReference(): bool
    {
        return $this->referenceLink !== null;
    }

    public function getReference(): Reference
    {
        return $this->getOwner()->getReference($this->referenceLink);
    }

    public function getPersistenceName(): string
    {
        return $this->actual ?? $this->shortName;
    }

    /**
     * Should this field use alias?
     */
    public function useAlias(): bool
    {
        return $this->actual !== null;
    }

    // }}}

    // {{{ Scope condition

    /**
     * Returns arguments to be used for query on this field based on the condition.
     *
     * @param string|null $operator one of Scope\Condition operators
     * @param mixed       $value    the condition value to be handled
     *
     * @return array{$this, string, mixed}
     */
    public function getQueryArguments($operator, $value): array
    {
        $typecastField = $this;
        if (in_array($operator, [
            Scope\Condition::OPERATOR_LIKE,
            Scope\Condition::OPERATOR_NOT_LIKE,
            Scope\Condition::OPERATOR_REGEXP,
            Scope\Condition::OPERATOR_NOT_REGEXP,
        ], true)) {
            $typecastField = new self(['type' => 'string']);
            $typecastField->setOwner(new Model($this->getOwner()->getPersistence(), ['table' => false]));
            $typecastField->shortName = $this->shortName;
        }

        if ($value instanceof Persistence\Array_\Action) { // needed to pass hintable tests
            $v = $value;
        } elseif (is_array($value)) {
            $v = array_map(static fn ($value) => $typecastField->typecastSaveField($value), $value);
        } else {
            $v = $typecastField->typecastSaveField($value);
        }

        return [$this, $operator, $v];
    }

    // }}}

    // {{{ Handy methods used by UI

    /**
     * Returns if field should be editable in UI.
     */
    public function isEditable(): bool
    {
        return $this->ui['editable'] ?? !$this->readOnly && !$this->neverPersist && !$this->system;
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
        return $this->caption ?? $this->ui['caption'] ?? $this->readableCaption($this->shortName);
    }

    // }}}

    /**
     * When field is used as expression, this method will be called.
     * Universal way to convert ourselves to expression. Off-load implementation into persistence.
     */
    public function getDsqlExpression(Expression $expression): Expression
    {
        $this->getOwner()->assertHasPersistence();
        if (!$this->getOwner()->getPersistence() instanceof Persistence\Sql) {
            throw (new Exception('Field must have SQL persistence if it is used as part of expression'))
                ->addMoreInfo('persistence', $this->getOwner()->getPersistence());
        }

        return $this->getOwner()->getPersistence()->getFieldSqlExpression($this, $expression);
    }

    /**
     * @return array<string, mixed>
     */
    public function __debugInfo(): array
    {
        $arr = [
            'ownerClass' => $this->issetOwner() ? get_class($this->getOwner()) : null,
            'shortName' => $this->shortName,
            'type' => $this->type,
        ];

        foreach ([
            'actual', 'neverPersist', 'neverSave', 'system', 'readOnly', 'ui', 'joinName',
        ] as $key) {
            if ($this->{$key} !== null) {
                $arr[$key] = $this->{$key};
            }
        }

        return $arr;
    }
}
