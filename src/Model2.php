<?php

declare(strict_types=1);

namespace Atk4\Data;

class Model2 extends Model
{
    /** @var string|self|false */
    public $_tableName;

    public function __construct(Persistence $persistence = null, array $defaults = [])
    {
        if (!isset($defaults['table'])) {
            $defaults = array_merge(['table' => $this->table], $defaults);
        }
        unset($this->{'table'});

        parent::__construct($persistence, $defaults);
    }

    protected function createInnerModel(): Model
    {
        return new Model2Inner($this->getPersistence(), [
            'table' => $this->_tableName,
            'idField' => $this->idField ? $this->getField($this->idField)->getPersistenceName() : false,
            'outerModelWeakref' => \WeakReference::create($this),
        ]);
    }

    public function __isset(string $name): bool
    {
        if ($name === 'table' && !$this->isEntity()) {
            return true;
        }

        return parent::__isset($name);
    }

    /**
     * @return mixed
     */
    public function &__get(string $name)
    {
        if ($name === 'table' && !$this->isEntity()) {
            $trace = debug_backtrace(\DEBUG_BACKTRACE_PROVIDE_OBJECT | \DEBUG_BACKTRACE_IGNORE_ARGS, 5);
            while (($trace[0]['object'] ?? null) === $this && ($trace[0]['function'] ?? null) === '__get') {
                array_shift($trace);
                $trace = array_values($trace);
            }
            $calledFromObject = $trace[0]['object'] ?? null;

            if ($this->_tableName === null || $this->_tableName === false) {
                $res = $this->_tableName;

                return $res;
            }

            $im = $this->createInnerModel();

            if ($calledFromObject instanceof Schema\Migrator // does not make much sense to support object table
                || is_subclass_of($calledFromObject, Model::class) // two uses directly in Model are fine, other uses may rely on string table justifiably
                || $calledFromObject instanceof Util\DeepCopy // not implemented, must match by class name + string table
            ) {
                return $im->table;
            }

            return $im;
        }

        return parent::__get($name);
    }

    /**
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        if ($name === 'table' && !$this->isEntity()) {
            if (is_scalar($value) || $value instanceof self || $value === null) {
                $this->_tableName = $value;

                return;
            }

            throw new \Error('Unexpected set call');
        }

        parent::__set($name, $value);
    }

    public function __unset(string $name): void
    {
        if ($name === 'table') {
            if ($this->isEntity()) {
                return;
            }

            throw new \Error('Unexpected unset call');
        }

        parent::__unset($name);
    }
}
