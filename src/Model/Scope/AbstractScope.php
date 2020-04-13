<?php

namespace atk4\data\Model\Scope;

use atk4\core\InitializerTrait;
use atk4\core\TrackableTrait;
use atk4\data\Model;

abstract class AbstractScope
{
    use InitializerTrait {
        init as _init;
    }
    use TrackableTrait;

    /**
     * Defines if scope should be applied or not.
     *
     * @var bool
     */
    protected $active = true;

    /**
     * The model this scope belongs to.
     *
     * @var Model
     */
    public $model;

    /**
     * Contains the placeholder registry in $key => $options format.
     *
     * @var array
     */
    protected static $placeholders = [];

    /**
     * Register placeholoder for a value to be replaced
     * The $options array may contain
     * - label : string - the label to use when converting toWords
     * - value : string|Callable - the actual value to be used when applying the scope
     * If value is Callable the it is called with $model, $scope as arguments.
     *
     * @param string          $key
     * @param string|callable $options
     */
    final public static function registerValuePlaceholder($key, $options)
    {
        self::$placeholders[$key] = is_array($options) ? $options : [
            'label' => $key,
            'value' => $options,
        ];
    }

    /**
     * Method is executed when the scope is added to Model using Model::add
     * $this->owner in this case is the Model object.
     */
    public function init()
    {
        $this->_init();

        $this->owner->scope()->addComponent($this);
    }

    /**
     * Use a model.
     *
     * @param Model $model
     *
     * @return static
     */
    final public function on(Model $model)
    {
        return $this->setModel(clone $model);
    }

    /**
     * Set the applicable model for the scope and its components.
     *
     * @param Model $model
     *
     * @return static
     */
    abstract public function setModel(Model $model = null);

    /**
     * Negate the scope object
     * e.g from 'is' to 'is not'.
     *
     * @return $this
     */
    abstract public function negate();

    /**
     * Method to lookup in scope for certain field conditions or condition objects.
     *
     * @param string|object $key key to find or condition object
     *
     * @return Condition[] array of conditions that match $keyOrCondition
     */
    abstract public function find($keyOrCondition);

    /**
     * Validate the values against the $this->model with applied this scope
     * Returns array of conditions not met.
     *
     * @param array $values
     *
     * @return Condition[] array of conditions the values did not validate against, empty array if valid
     */
    abstract public function validate($values);

    /**
     * Return if scope has any conditions.
     *
     * @return bool
     */
    abstract public function isEmpty();

    /**
     * Convert the scope to human readable words when applied on $model.
     *
     * @param bool $asHtml
     */
    abstract public function toWords($asHtml = false);

    /**
     * Peels off nested scopes with single contained component.
     * Useful for convert (((field = value))) to field = value.
     *
     * @return AbstractScope
     */
    public function peel()
    {
        return $this;
    }

    /**
     * Sets the scope as excluded from applying it to the model.
     *
     * @return $this
     */
    public function deactivate()
    {
        $this->active = false;

        return $this;
    }

    /**
     * Sets the scope as included for applying it to the model.
     *
     * @return $this
     */
    public function activate()
    {
        $this->active = true;

        return $this;
    }

    /**
     * Returns is scope should be applied to the model.
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->active && !$this->isEmpty();
    }

    /**
     * Returns if scope contains several conditions.
     *
     * @return bool
     */
    public function isCompound()
    {
        return false;
    }

    public static function __set_state($array)
    {
        $scope = new static();

        foreach ($array as $property => $value) {
            $scope->{$property} = $value;
        }

        return $scope;
    }
}
