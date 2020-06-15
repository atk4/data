<?php

declare(strict_types=1);

namespace atk4\data;

/**
 * Trait to add array like support to Model, example usage:
 * class CustomModel extends \atk4\data\Model implements \ArrayAccess
 * {
 *     use \atk4\data\ModelArrayAccessTrait;
 * }.
 */
trait ModelArrayAccessTrait
{
    /**
     * Does field exist?
     *
     * @param string $name
     *
     * @return bool
     */
    public function offsetExists($name)
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
    public function offsetSet($name, $val)
    {
        $this->set($name, $val);
    }

    /**
     * Redo field value.
     *
     * @param string $name
     */
    public function offsetUnset($name)
    {
        $this->_unset($name);
    }
}
