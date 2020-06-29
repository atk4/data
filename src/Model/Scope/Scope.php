<?php

declare(strict_types=1);

namespace atk4\data\Model\Scope;

use atk4\data\Exception;
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

    /**
     * Get array of all components.
     *
     * @return AbstractScope[]
     */
    public function getAllComponents(): array
    {
        return $this->components;
    }

    /**
     * Get array of only active components.
     *
     * @return AbstractScope[]
     */
    public function getActiveComponents(): array
    {
        return array_filter($this->components, function (AbstractScope $scope) {
            return $scope->isActive();
        });
    }

    public function setModel(Model $model = null)
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
     * @return static
     */
    public function addComponent(AbstractScope $scope)
    {
        $this->components[] = $scope->setModel($this->model);

        return $this;
    }

    public function isEmpty(): bool
    {
        return empty($this->getActiveComponents());
    }

    public function isCompound(): bool
    {
        return count($this->getActiveComponents()) > 1;
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

    public function simplify()
    {
        $activeComponents = $this->getActiveComponents();

        if (count($activeComponents) != 1) {
            return $this;
        }

        /**
         * @var AbstractScope $component
         */
        $component = reset($activeComponents);

        return $component->simplify();
    }

    public function validate(array $values): array
    {
        if (!$this->isActive()) {
            return [];
        }

        if (!$model = $this->model) {
            throw new Exception('Model must be set using setModel to validate');
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

    public function toWords(bool $asHtml = false): string
    {
        if (!$this->isActive()) {
            return '';
        }

        $parts = [];
        foreach ($this->components as $scope) {
            $words = $scope->on($this->model)->toWords($asHtml);

            $parts[] = $this->isCompound() && $scope->isCompound() ? "({$words})" : $words;
        }

        $glue = ' ' . strtolower($this->junction) . ' ';

        return implode($glue, $parts);
    }

    /**
     * Create a scope from array of scopes or arrays.
     *
     * @param string|array $scopes
     * @param string       $junction
     *
     * @return static
     */
    public function __construct($scopes = null, $junction = self::AND)
    {
        // use one of JUNCTIONS values, otherwise $junction is truish means OR, falsish means AND
        $this->junction = in_array($junction, self::JUNCTIONS, true) ? $junction : self::JUNCTIONS[$junction ? 1 : 0];

        // handle it with Condition if it is a string
        if (is_string($scopes)) {
            $scopes = new Condition($scopes);
        }

        // true means no conditions, false means no access to any records at all
        if (is_bool($scopes)) {
            $scopes = $scopes ? [] : new Condition(false);
        }

        if (!$scopes) {
            return;
        }

        $scopes = (array) $scopes;

        foreach ($scopes as $scope) {
            $scope = is_string($scope) ? new Condition($scope) : $scope;

            if (is_array($scope)) {
                // array of OR sub-scopes
                if (count($scope) === 1 && isset($scope[0]) && is_array($scope[0])) {
                    $scope = new static($scope[0], self::OR);
                } else {
                    $scope = new Condition(...$scope);
                }
            }

            if ($scope->isEmpty()) {
                continue;
            }

            $this->addComponent(clone $scope);
        }
    }

    public function find($key): array
    {
        $ret = [];
        foreach ($this->components as $cc) {
            if (is_object($key)) {
                if ($cc == $key) {
                    $ret[] = $cc;
                } elseif ($cc instanceof AbstractScope) {
                    $ret = array_merge($ret, (array) $cc->find($key));
                }
            } else {
                $ret = array_merge($ret, (array) $cc->find($key));
            }
        }

        return $ret ?: [];
    }

    /**
     * Merge $scope into current scope AND as junction.
     *
     * @return static
     */
    public function and(AbstractScope $scope)
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
     * @return static
     */
    public function or(AbstractScope $scope)
    {
        $self = clone $this;

        $this->junction = self::OR;

        $this->components = [$self, $scope];

        return $this->setModel($this->model);
    }

    /**
     * Merge number of scopes using AND as junction.
     *
     * @param AbstractScope $_
     *
     * @return Scope
     */
    public static function mergeAnd(AbstractScope $scopeA, AbstractScope $scopeB, $_ = null)
    {
        return new static(func_get_args(), self::AND);
    }

    /**
     * Merge number of scopes using OR as junction.
     *
     * @param AbstractScope $_
     *
     * @return Scope
     */
    public static function mergeOr(AbstractScope $scopeA, AbstractScope $scopeB, $_ = null)
    {
        return new static(func_get_args(), self::OR);
    }

    /**
     * Merge two scopes using $junction.
     *
     * @param string|bool $junction
     *
     * @return Scope
     */
    public static function merge(AbstractScope $scopeA, AbstractScope $scopeB, $junction = self::AND)
    {
        return new static([$scopeA, $scopeB], $junction);
    }
}
