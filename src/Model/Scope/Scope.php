<?php

namespace atk4\data\Model\Scope;

use atk4\data\Model;
use atk4\data\Exception;

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

    /**
     * Get array of all components.
     *
     * @return array
     */
    public function getAllComponents()
    {
        return $this->components;
    }

    /**
     * Get array of only active components.
     *
     * @return array
     */
    public function getActiveComponents()
    {
        return array_filter($this->components, function (AbstractScope $scope) {
            return $scope->isActive();
        });
    }

    public function setModel(?Model $model = null)
    {
        $this->model = $model;

        foreach ($this->components as $scope) {
            $scope->setModel($model);
        }

        return $this;
    }

    /**
     * Add a scope as component to this scope.
     *
     * @param AbstractScope $scope
     *
     * @return static
     */
    public function addComponent(AbstractScope $scope)
    {
        $this->components[] = $scope->setModel($this->model);

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

    public function getJunction()
    {
        return $this->junction;
    }

    /**
     * Checks if junction is OR.
     *
     * @return bool
     */
    public function any()
    {
        return $this->junction === self::OR;
    }

    /**
     * Checks if junction is AND.
     *
     * @return bool
     */
    public function all()
    {
        return $this->junction === self::AND;
    }

    /**
     * Clears the scope from components.
     *
     * @return static
     */
    public function clear()
    {
        $this->components = [];

        return $this;
    }

    public function __clone()
    {
        foreach ($this->components as $k => $scope) {
            $this->components[$k] = clone $scope;
        }
    }

    public function peel()
    {
        $activeComponents = $this->getActiveComponents();

        if (count($activeComponents) != 1) {
            return $this;
        }

        $component = reset($activeComponents);

        return $component->peel();
    }

    public function validate($values)
    {
        if (!$this->isActive()) {
            return [];
        }

        if (!$model = $this->model) {
            throw new Exception(['Model must be set using setModel to validate']);
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

    public function toWords($asHtml = false)
    {
        if (!$this->isActive()) {
            return '';
        }

        $parts = [];
        foreach ($this->components as $scope) {
            $words = $scope->on($this->model)->toWords($asHtml);

            $parts[] = $this->isCompound() && $scope->isCompound() ? "($words)" : $words;
        }

        $glue = ' '.strtolower($this->junction).' ';

        return implode($glue, $parts);
    }

    /**
     * Create a scope from array of scopes or arrays.
     *
     * @param mixed  $scopeOrArray
     * @param string $junction
     *
     * @return static
     */
    public static function create($scopeOrArray = null, $junction = self::AND)
    {
        if ($scopeOrArray instanceof AbstractScope) {
            return $scopeOrArray;
        }

        return new static ($scopeOrArray, $junction);
    }

    public function __construct($scopes = null, $junction = self::AND)
    {
        // use one of JUNCTIONS values, otherwise $junction is truish means OR, falsish means AND
        $this->junction = in_array($junction, self::JUNCTIONS) ? $junction : self::JUNCTIONS[$junction ? 1 : 0];

        // handle it with Condition if it is a string
        if (is_string($scopes)) {
            $scopes = Condition::create($scopes);
        }

        // true means no conditions, false means no access to any records at all
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
                // array of OR sub-scopes
                if (count($scope) === 1 && isset($scope[0]) && is_array($scope[0])) {
                    $scope = self::create($scope[0], self::OR);
                } else {
                    $scope = Condition::create(...$scope);
                }
            }

            if ($scope->isEmpty()) {
                continue;
            }

            $this->addComponent(clone $scope);
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

    /**
     * Merge $scope into current scope AND as junction.
     *
     * @param AbstractScope|array $scope
     *
     * @return static
     */
    public function and($scope)
    {
        if ($this->junction == self::OR) {
            $self = clone $this;

            $this->junction = self::AND;

            $this->components = [];

            $this->addComponent($self);
        }

        return $this->addComponent($scope);
    }

    /**
     * Merge $scope into current scope OR as junction.
     *
     * @param AbstractScope|array $scope
     *
     * @return static
     */
    public function or($scope)
    {
        $self = clone $this;

        $this->junction = self::OR;

        $this->components = [$self, $scope];

        return $this->setModel($this->model);
    }

    /**
     * Merge number of scopes using AND as junction.
     *
     * @param AbstractScope $scopeA
     * @param AbstractScope $scopeB
     * @param AbstractScope $_
     *
     * @return Scope
     */
    public static function mergeAnd(AbstractScope $scopeA, AbstractScope $scopeB, $_ = null)
    {
        return self::create(func_get_args(), self::AND);
    }

    /**
     * Merge number of scopes using OR as junction.
     *
     * @param AbstractScope $scopeA
     * @param AbstractScope $scopeB
     * @param AbstractScope $_
     *
     * @return Scope
     */
    public static function mergeOr(AbstractScope $scopeA, AbstractScope $scopeB, $_ = null)
    {
        return self::create(func_get_args(), self::OR);
    }

    /**
     * Merge two scopes using $junction.
     *
     * @param AbstractScope $scopeA
     * @param AbstractScope $scopeB
     * @param string|bool   $junction
     *
     * @return Scope
     */
    public static function merge(AbstractScope $scopeA, AbstractScope $scopeB, $junction = self::AND)
    {
        return self::create([$scopeA, $scopeB], $junction);
    }
}
