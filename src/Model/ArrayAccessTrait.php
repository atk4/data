<?php

declare(strict_types=1);

namespace atk4\data\Model;

/**
 * Trait to add array like support to Model, example usage:
 * class CustomModel extends \atk4\data\Model implements \ArrayAccess
 * {
 *     use \atk4\data\Model\ArrayAccessTrait;
 * }.
 */
trait ArrayAccessTrait
{
    /**
     * Does field exist?
     *
     * @param string $name
     */
    public function offsetExists($name): bool
    {
        return $this->_isset($name);
    }

    /**
     * Returns field value.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function offsetGet($name)
    {
        return $this->get($name);
    }

    /**
     * Set field value.
     *
     * @param string $name
     * @param mixed  $val
     */
    public function offsetSet($name, $val): void
    {
        $this->set($name, $val);
    }

    /**
     * Redo field value.
     *
     * @param string $name
     */
    public function offsetUnset($name): void
    {
        $this->_unset($name);
    }
}
