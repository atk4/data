<?php

declare(strict_types=1);

namespace atk4\data\Model\Scope;

use atk4\core\ContainerTrait;
use atk4\data\Exception;
use atk4\data\Model;

/**
 * @property AbstractCondition[] $elements
 */
class CompoundCondition extends AbstractCondition
{
    use ContainerTrait;

    // junction definitions
    public const OR = 'OR';
    public const AND = 'AND';

    /**
     * Junction to use in case more than one element.
     *
     * @var self::AND|self::OR
     */
    protected $junction = self::AND;

    /**
     * Create a CompoundCondition from array of condition objects or condition arrays.
     *
     * @param AbstractCondition[]|array[] $nestedConditions
     */
    public function __construct(array $nestedConditions = [], string $junction = self::AND)
    {
        if (!in_array($junction, [self::OR, self::AND], true)) {
            throw (new Exception('Using invalid CompondCondition junction'))
                ->addMoreInfo('junction', $junction);
        }

        $this->junction = $junction;

        foreach ($nestedConditions as $nestedCondition) {
            if ($nestedCondition instanceof AbstractCondition) {
                $condition = $nestedCondition;
            } else {
                $condition = new Condition(...(array) $nestedCondition);
            }

            $this->add($condition);
        }
    }

    public function __clone()
    {
        foreach ($this->elements as $k => $nestedCondition) {
            $this->elements[$k] = clone $nestedCondition;
            $this->elements[$k]->owner = $this;
            $this->elements[$k]->short_name = $nestedCondition->short_name;
        }
        $this->owner = null;
        $this->short_name = null;
    }

    /**
     * @return $this
     */
    public function addCondition($field, $operator = null, $value = null)
    {
        if (func_num_args() === 1 && $field instanceof AbstractCondition) {
            $condition = $field;
        } elseif (func_num_args() === 1 && is_array($field)) {
            $condition = static::createAnd(func_get_args());
        } else {
            $condition = new Condition(...func_get_args());
        }

        $this->add($condition);

        return $this;
    }

    /**
     * Return array of nested conditions.
     *
     * @return AbstractCondition[]
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

    public function simplify(): AbstractCondition
    {
        if (count($this->elements) !== 1) {
            return $this;
        }

        /** @var AbstractCondition $component */
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
        $this->junction = $this->junction == self::OR ? self::AND : self::OR;

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
     * @return static
     */
    public static function createAnd(...$conditions)
    {
        return new static($conditions, self::AND);
    }

    /**
     * @return static
     */
    public static function createOr(...$conditions)
    {
        return new static($conditions, self::OR);
    }
}
