<?php

declare(strict_types=1);

namespace atk4\data\Model\Scope;

use atk4\core\ContainerTrait;

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
     * Array of valid junctions.
     *
     * @var array
     */
    public const JUNCTIONS = [
        self::AND,
        self::OR,
    ];

    /**
     * Junction to use in case more than one element.
     *
     * @var self::AND|self::OR
     */
    protected $junction = self::AND;

    /**
     * Create a CompoundCondition from array of condition objects or condition arrays.
     */
    public function __construct(array $nestedConditions = [], $junction = self::AND)
    {
        // use one of JUNCTIONS values, otherwise $junction is truish means OR, falsish means AND
        $this->junction = in_array($junction, self::JUNCTIONS, true) ? $junction : self::JUNCTIONS[$junction ? 1 : 0];

        foreach ($nestedConditions as $nestedCondition) {
            $nestedCondition = is_string($nestedCondition) ? new BasicCondition($nestedCondition) : $nestedCondition;

            if (is_array($nestedCondition)) {
                // array of OR nested conditions
                if (count($nestedCondition) === 1 && isset($nestedCondition[0]) && is_array($nestedCondition[0])) {
                    $nestedCondition = new self($nestedCondition[0], self::OR);
                } else {
                    $nestedCondition = new BasicCondition(...$nestedCondition);
                }
            }

            if ($nestedCondition->isEmpty()) {
                continue;
            }

            $this->add(clone $nestedCondition);
        }
    }

    public function __clone()
    {
        foreach ($this->elements as $k => $nestedCondition) {
            $this->elements[$k] = clone $nestedCondition;
            $this->elements[$k]->owner = $this;
        }
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

    public function onChangeModel(): void
    {
        foreach ($this->elements as $nestedCondition) {
            $nestedCondition->onChangeModel();
        }
    }

    public function isEmpty(): bool
    {
        return empty($this->elements);
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
     * Clears the group from nested conditions.
     *
     * @return static
     */
    public function clear()
    {
        foreach ($this->elements as $nestedCondition) {
            $nestedCondition->destroy();
        }

        return $this;
    }

    public function simplify()
    {
        if (count($this->elements) != 1) {
            return $this;
        }

        /**
         * @var AbstractCondition $component
         */
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

    public function toWords(bool $asHtml = false): string
    {
        if ($this->isEmpty()) {
            return '';
        }

        $parts = [];
        foreach ($this->elements as $nestedCondition) {
            $words = $nestedCondition->toWords($asHtml);

            $parts[] = $this->isCompound() && $nestedCondition->isCompound() ? "({$words})" : $words;
        }

        $glue = ' ' . strtolower($this->junction) . ' ';

        return implode($glue, $parts);
    }

    /**
     * Merge number of conditions using AND as junction.
     *
     * @param AbstractCondition $_
     *
     * @return static
     */
    public static function mergeAnd(AbstractCondition $conditionA, AbstractCondition $conditionB, $_ = null)
    {
        return new self(func_get_args(), self::AND);
    }

    /**
     * Merge number of conditions using OR as junction.
     *
     * @param AbstractCondition $_
     *
     * @return static
     */
    public static function mergeOr(AbstractCondition $conditionA, AbstractCondition $conditionB, $_ = null)
    {
        return new self(func_get_args(), self::OR);
    }
}
