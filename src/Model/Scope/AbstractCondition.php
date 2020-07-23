<?php

declare(strict_types=1);

namespace atk4\data\Model\Scope;

use atk4\core\InitializerTrait;
use atk4\core\TrackableTrait;
use atk4\data\Exception;
use atk4\data\Model;

/**
 * @property CompoundCondition $owner
 */
abstract class AbstractCondition
{
    use InitializerTrait {
        init as _init;
    }
    use TrackableTrait;

    /**
     * Method is executed when the scope is added to parent scope using Scope::add
     * $this->owner is the Scope object.
     */
    public function init(): void
    {
        if (!$this->owner instanceof self) {
            throw new Exception('Scope can only be added as element to scope');
        }

        $this->_init();

        $this->onChangeModel();
    }

    abstract protected function onChangeModel();

    /**
     * Get the model this condition is associated with.
     */
    public function getModel(): ?Model
    {
        return $this->owner ? $this->owner->getModel() : null;
    }

    /**
     * Empty the scope object.
     *
     * @return static
     */
    abstract public function clear();

    /**
     * Negate the scope object
     * e.g from 'is' to 'is not'.
     *
     * @return static
     */
    abstract public function negate();

    /**
     * Return if scope has any conditions.
     */
    abstract public function isEmpty(): bool;

    /**
     * Convert the scope to human readable words when applied on $model.
     *
     * @return bool
     */
    abstract public function toWords(Model $model = null): string;

    /**
     * Simplifies by peeling off nested group conditions with single contained component.
     * Useful for converting (((field = value))) to field = value.
     */
    public function simplify(): self
    {
        return $this;
    }

    /**
     * Returns if scope contains several conditions.
     */
    public function isCompound(): bool
    {
        return false;
    }
}
