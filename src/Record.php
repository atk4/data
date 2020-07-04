<?php

declare(strict_types=1);

namespace atk4\data;

/**
 * Currently very coupled with Model, but all record related/loaded data should be stored here.
 */
class Record
{
    /** @var Model To be used from Model class only, later remove in advance of WeakMap in model */
    private $_model;

    /**
     * @param mixed $id
     */
    public function __construct(Model $model, $id = null)
    {
        $this->_model = $model;

        $isNew = func_num_args() === 1;
    }

    // {{{ Model related magic methods - map unported properties/methods to owning model

    public function __isset(string $name): bool
    {
        if (!property_exists($this, $name) && $this->_model->__isset($name)) {
            return true;
        }

        return isset($this->{$name});
    }

    /**
     * @return mixed
     */
    public function &__get(string $name)
    {
        if (!property_exists($this, $name) && $this->_model->__isset($name)) {
            $model = $this->_model;

            return \Closure::bind(static function &() use ($model, $name) {
                return $model->{$name};
            }, null, $model)();
        }

        return $this->{$name};
    }

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        if (!property_exists($this, $name) && $this->_model->__isset($name)) {
            $model = $this->_model;

            \Closure::bind(static function () use ($model, $name, $value) {
                $model->{$name} = $value;
            }, null, $model)();

            return;
        }

        $this->{$name} = $value;
    }

    public function __unset(string $name): void
    {
        if (!property_exists($this, $name) && $this->_model->__isset($name)) {
            throw new Exception('Model related magic properties are not allowed to be unset');
        }

        unset($this->{$name});
    }

    /**
     * @return mixed
     */
    public function __call(string $name, $args)
    {
        $nameMigr = '_migrtorecord_' . $name;
        if (method_exists($this->_model, $nameMigr)) {
            $name = $nameMigr;
        }
        unset($nameMigr);

        if (method_exists($this->_model, $name)) {
            $model = $this->_model;

            return \Closure::bind(static function () use ($model, $name, $args) {
                return $model->{$name}(...$args);
            }, null, $model)();
        }

        throw new Exception('Method "' . $name . '" does not exist');
    }

    // }}}
}
