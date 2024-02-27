<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Scope;

use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Exception;
use Atk4\Data\Model;

/**
 * @method Model\Scope getOwner()
 */
abstract class AbstractScope
{
    use InitializerTrait {
        init as private _init;
    }
    use TrackableTrait {
        setOwner as private _setOwner;
    }
    use WarnDynamicPropertyTrait;

    /**
     * @param Model\Scope $owner
     *
     * @return $this
     */
    public function setOwner(object $owner)
    {
        if (!$owner instanceof self) { // @phpstan-ignore-line
            throw new Exception('Scope can only be added as element to scope');
        }

        return $this->_setOwner($owner);
    }

    /**
     * Method is executed when the scope is added to parent scope using Scope::add().
     */
    protected function init(): void
    {
        $this->_init();

        $this->onChangeModel();
    }

    abstract protected function onChangeModel(): void;

    /**
     * Get the model this condition is associated with.
     */
    public function getModel(): ?Model
    {
        return $this->issetOwner() ? $this->getOwner()->getModel() : null;
    }

    /**
     * Empty the scope object.
     *
     * @return $this
     */
    abstract public function clear(): self;

    /**
     * Negate the scope object e.g. from '=' to '!='.
     *
     * @return $this
     */
    abstract public function negate(): self;

    /**
     * Return if scope has any conditions.
     */
    abstract public function isEmpty(): bool;

    /**
     * Convert the scope to human readable words when applied on $model.
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
