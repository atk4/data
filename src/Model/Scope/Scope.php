<?php

namespace atk4\data\Model\Scope;

use atk4\data\Model;

class Scope extends AbstractScope
{
    // junction definitions
    const OR = 'OR';
    const AND = 'AND';

    /**
     * Array of valid junctions.
     *
     * @var array
     */
    const JUNCTIONS = [
        self::AND,
        self::OR,
    ];

    /**
     * Array of contained components.
     *
     * @var AbstractScope[]
     */
    protected $components = [];

    /**
     * Junction to use in case more than one component.
     *
     * @var self::AND|self::OR
     */
    protected $junction = self::AND;

    public function getConditions(Model $model)
    {
        $conditions = [];
        foreach ($this->getActiveComponents() as $scope) {
            $conditions = array_merge($conditions, $scope->getConditions($model));
        }

        return $this->junction == self::OR ? [[$conditions]] : $conditions;
    }

    public function getActiveComponents()
    {
        return array_filter($this->components, function (AbstractScope $scope) {
            return $scope->isActive();
        });
    }

    public function addComponent(AbstractScope $scope)
    {
        $this->components[] = $scope;

        return $this;
    }

    public function isEmpty()
    {
        return empty($this->getActiveComponents());
    }

    public function isCompound()
    {
        return count($this->getActiveComponents()) > 1;
    }

    public function __clone()
    {
        foreach ($this->components as $k => $scope) {
            $this->components[$k] = clone $scope;
        }
    }

    public function validate(Model $model, $values)
    {
        if (!$this->isActive()) {
            return [];
        }

        $values = is_numeric($id = $values) ? $model->load($id)->get() : $values;

        $issues = [];
        foreach ($this->getActiveComponents() as $scope) {
            $issues = array_merge($issues, (array) $scope->validate($model, $values));
        }

        return $issues;
    }

    /**
     * Use De Morgan's laws to negate.
     *
     * @return $this
     */
    public function negate()
    {
        $this->junction = $this->junction == self::OR ? self::AND : self::OR;

        foreach ($this->components as $scope) {
            $scope->negate();
        }

        return $this;
    }

    public function toWords(Model $model, $asHtml = true)
    {
        if (!$this->isActive()) {
            return '';
        }

        $parts = [];
        foreach ($this->components as $scope) {
            $words = $scope->toWords($model, $asHtml);

            $parts[] = $this->isCompound() && $scope->isCompound() ? "($words)" : $words;
        }

        $glue = ' '.strtolower($this->junction).' ';

        return implode($glue, $parts);
    }

    /**
     * Create a scope from array of scopes or arrays.
     *
     * @param mixed $scopeOrArray
     * @param bool  $or
     *
     * @return static
     */
    public static function create($scopeOrArray = null, $junction = self::AND)
    {
        if ($scopeOrArray instanceof AbstractScope) {
            return $scopeOrArray;
        }

        $scopeOrArray = is_string($scopeOrArray) ? Condition::create($scopeOrArray) : $scopeOrArray;

        return new static ($scopeOrArray, $junction);
    }

    public function __construct($scopes = null, $junction = self::AND)
    {
        if (is_bool($scopes)) {
            $scopes = $scopes ? [] : Condition::create(false);
        }

        if (!$scopes) {
            return;
        }

        $scopes = (array) $scopes;

        foreach ($scopes as $scope) {
            $scope = is_string($scope) ? Condition::create($scope) : $scope;

            if (is_array($scope)) {
                if (count($scope) === 1) {
                    if (is_array($scope[0])) {
                        $scope = self::create($scope[0], self::OR);
                    } else {
                        $scope = Condition::create(...$scope);
                    }
                } else {
                    $scope = Condition::create(...$scope); // self::create($scope, self::AND);
                }
            }

            if ($scope->isEmpty()) {
                continue;
            }

            $this->addComponent($scope);
        }

        if (count($scopes) > 1) {
            $this->junction = in_array($junction, self::JUNCTIONS) ? $junction : self::JUNCTIONS[$junction ? 1 : 0];
        }
    }

    public function find($key)
    {
        $ret = [];
        foreach ($this->components as $cc) {
            if (is_object($key)) {
                if ($cc == $key) {
                    $ret[] = $cc;
                } elseif ($cc instanceof AbstractScope) {
                    $scope = $cc->find($key);
                    if (is_array($scope)) {
                        $ret = array_merge($ret, $scope);
                    }
                }
            } else {
                $scope = $cc->find($key);
                if (is_array($scope)) {
                    $ret = array_merge($ret, $scope);
                } elseif (!is_null($scope)) {
                    $ret[] = $scope;
                }
            }
        }

        return $ret ?: null;
    }

    public static function and($scopeA, $scopeB, $_ = null)
    {
        return self::create(func_get_args(), self::AND);
    }

    public static function or($scopeA, $scopeB, $_ = null)
    {
        return self::create(func_get_args(), self::OR);
    }

    public static function merge($scopeA, $scopeB, $junction = self::AND)
    {
        return self::create([$scopeA, $scopeB], $junction);
    }

    /**
     * Get the scope of the model.
     *
     * @param Model $model
     *
     * @return static
     */
    public static function of(Model $model)
    {
        return self::create($model->conditions);
        $scope = static::create();
        foreach ($model->conditions as $args) {
            $scope = self::and($scope, is_array($args[0]) ? self::or(...$args[0]) : self::and(...$args));
        }

        return $scope;
    }
}
