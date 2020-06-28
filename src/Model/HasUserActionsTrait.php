<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\data\Model;

trait HasUserActionsTrait
{
    /**
     * Default class for addUserAction().
     *
     * @var string|array
     */
    public $_default_seed_action = [Model\UserAction::class];

    /**
     * @var array Collection of user actions - using key as action system name
     */
    protected $userActions = [];

    /**
     * Register new user action for this model. By default UI will allow users to trigger actions
     * from UI.
     *
     * @param string         $name     Action name
     * @param array|callable $defaults
     */
    public function addUserAction($name, $defaults = []): Model\UserAction
    {
        if (is_callable($defaults)) {
            $defaults = ['callback' => $defaults];
        }

        if (!isset($defaults['caption'])) {
            $defaults['caption'] = $this->readableCaption($name);
        }

        /** @var Model\UserAction $action */
        $action = $this->factory($this->_default_seed_action, $defaults);

        $this->_addIntoCollection($name, $action, 'userActions');

        return $action;
    }

    /**
     * Returns list of actions for this model. Can filter actions by scope.
     * It will also skip system user actions (where system === true).
     *
     * @param int $scope e.g. Model\UserAction::SCOPE_ALL
     */
    public function getUserActions($scope = null): array
    {
        return array_filter($this->userActions, function ($action) use ($scope) {
            return !$action->system && ($scope === null || $action->scope === $scope);
        });
    }

    /**
     * Returns true if user action with a corresponding name exists.
     *
     * @param string $name UserAction name
     */
    public function hasUserAction($name): bool
    {
        return $this->_hasInCollection($name, 'userActions');
    }

    /**
     * Returns one action object of this model. If action not defined, then throws exception.
     *
     * @param string $name Action name
     */
    public function getUserAction($name): Model\UserAction
    {
        return $this->_getFromCollection($name, 'userActions');
    }

    /**
     * Execute specified action with specified arguments.
     *
     * @param string $name UserAction name
     */
    public function executeUserAction($name, ...$args)
    {
        $this->getUserAction($name)->execute(...$args);
    }

    /**
     * Remove specified action(s).
     *
     * @param string|array $name
     *
     * @return $this
     */
    public function removeUserAction($name)
    {
        foreach ((array) $name as $action) {
            $this->_removeFromCollection($action, 'userActions');
        }

        return $this;
    }

    /**
     * @deprecated use addUserAction instead
     */
    public function addAction($name, $defaults = []): Model\UserAction
    {
        return $this->addUserAction(...func_get_args());
    }

    /**
     * @deprecated use getUserActions instead
     */
    public function getActions($scope = null): array
    {
        return $this->getUserActions(...func_get_args());
    }

    /**
     * @deprecated use hasUserAction instead
     */
    public function hasAction($name): bool
    {
        return $this->hasUserAction(...func_get_args());
    }

    /**
     * @deprecated use hasUserAction instead
     */
    public function getAction($name): Model\UserAction
    {
        return $this->getUserAction(...func_get_args());
    }

    /**
     * @deprecated use executeUserAction instead
     */
    public function executeAction($name, ...$args)
    {
        $this->executeUserAction(...func_get_args());
    }

    /**
     * @deprecated use removeUserAction instead
     */
    public function removeAction($name)
    {
        return $this->removeUserAction(...func_get_args());
    }
}
