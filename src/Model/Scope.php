<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\ContainerTrait;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Model\Scope\AbstractScope;
use Atk4\Data\Persistence\Sql\Expressionable;

/**
 * @property array<AbstractScope> $elements
 */
class Scope extends AbstractScope
{
    use ContainerTrait;

    // junction definitions
    public const OR = 'OR';
    public const AND = 'AND';

    /** @var self::AND|self::OR Junction to use in case more than one element. */
    protected $junction = self::AND;

    /**
     * Create a Scope from array of condition objects or condition arrays.
     *
     * @param array<int, AbstractScope|Expressionable|array{string|Expressionable, 1?: mixed, 2?: mixed}> $conditions
     */
    public function __construct(array $conditions = [], string $junction = self::AND)
    {
        if (!in_array($junction, [self::OR, self::AND], true)) {
            throw (new Exception('Using invalid CompondCondition junction'))
                ->addMoreInfo('junction', $junction);
        }

        $this->junction = $junction;

        foreach ($conditions as $condition) {
            if (!$condition instanceof AbstractScope) {
                if ($condition instanceof Expressionable && !$condition instanceof Field) {
                    $condition = [$condition];
                }
                $condition = new Scope\Condition(...$condition);
            }

            $this->add($condition);
        }
    }

    public function __clone()
    {
        foreach ($this->elements as $k => $nestedCondition) {
            $this->elements[$k] = clone $nestedCondition;
            if ($this->elements[$k]->issetOwner()) {
                $this->elements[$k]->unsetOwner();
            }
            $this->elements[$k]->setOwner($this);
            $this->elements[$k]->shortName = $nestedCondition->shortName;
        }
        if ($this->issetOwner()) {
            $this->unsetOwner();
        }
        $this->shortName = null; // @phpstan-ignore-line
    }

    /**
     * @param AbstractScope|array<int, AbstractScope|Expressionable|array{string|Expressionable, 1?: mixed, 2?: mixed}>|string|Expressionable $field
     * @param ($field is string|Expressionable ? ($value is null ? mixed : string) : never)                                                   $operator
     * @param ($operator is string ? mixed : never)                                                                                           $value
     *
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        if ('func_num_args'() === 1 && $field instanceof AbstractScope) {
            $condition = $field;
        } elseif ('func_num_args'() === 1 && is_array($field)) {
            $condition = static::createAnd(...$field);
        } else {
            $condition = new Scope\Condition(...'func_get_args'());
        }

        $this->add($condition);

        return $this;
    }

    /**
     * Return array of nested conditions.
     *
     * @return array<AbstractScope>
     */
    public function getNestedConditions()
    {
        return $this->elements;
    }

    protected function onChangeModel(): void
    {
        foreach ($this->elements as $nestedCondition) {
            $nestedCondition->onChangeModel();
        }
    }

    public function isEmpty(): bool
    {
        return count($this->elements) === 0;
    }

    public function isCompound(): bool
    {
        return count($this->elements) > 1;
    }

    /**
     * @return self::AND|self::OR
     */
    public function getJunction(): string
    {
        return $this->junction;
    }

    /**
     * Checks if junction is OR.
     */
    public function isOr(): bool
    {
        return $this->junction === self::OR;
    }

    /**
     * Checks if junction is AND.
     */
    public function isAnd(): bool
    {
        return $this->junction === self::AND;
    }

    /**
     * Clears the compound condition from nested conditions.
     *
     * @return static
     */
    public function clear()
    {
        $this->elements = [];

        return $this;
    }

    public function simplify(): AbstractScope
    {
        if (count($this->elements) !== 1) {
            return $this;
        }

        $component = reset($this->elements);

        return $component->simplify();
    }

    /**
     * Use De Morgan's laws to negate.
     *
     * @return static
     */
    public function negate()
    {
        $this->junction = $this->junction === self::OR ? self::AND : self::OR;

        foreach ($this->elements as $nestedCondition) {
            $nestedCondition->negate();
        }

        return $this;
    }

    public function toWords(Model $model = null): string
    {
        $parts = [];
        foreach ($this->elements as $nestedCondition) {
            $words = $nestedCondition->toWords($model);

            $parts[] = $this->isCompound() && $nestedCondition->isCompound() ? '(' . $words . ')' : $words;
        }

        $glue = ' ' . strtolower($this->junction) . ' ';

        return implode($glue, $parts);
    }

    /**
     * @param AbstractScope|Expressionable|array{string|Expressionable, 1?: mixed, 2?: mixed} ...$conditions
     *
     * @return static
     */
    public static function createAnd(...$conditions)
    {
        return new static($conditions, self::AND);
    }

    /**
     * @param AbstractScope|Expressionable|array{string|Expressionable, 1?: mixed, 2?: mixed} ...$conditions
     *
     * @return static
     */
    public static function createOr(...$conditions)
    {
        return new static($conditions, self::OR);
    }
}
