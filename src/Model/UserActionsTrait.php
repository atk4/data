<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\data\Model;

trait UserActionsTrait
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
     * Returns list of actions for this model. Can filter actions by records they apply to.
     * It will also skip system user actions (where system === true).
     *
     * @param string $appliesTo e.g. Model\UserAction::APPLIES_TO_ALL_RECORDS
     */
    public function getUserActions($appliesTo = null): array
    {
        return array_filter($this->userActions, function ($action) use ($appliesTo) {
            return !$action->system && ($appliesTo === null || $action->appliesTo === $appliesTo);
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
     * @deprecated use addUserAction instead - will be removed in dec-2020
     */
    public function addAction($name, $defaults = []): Model\UserAction
    {
        'trigger_error'('Method Model::addAction is deprecated. Use Model::addUserAction instead', E_USER_DEPRECATED);

        return $this->addUserAction(...func_get_args());
    }

    /**
     * @deprecated use getUserActions instead - will be removed in dec-2020
     */
    public function getActions($scope = null): array
    {
        'trigger_error'('Method Model::getActions is deprecated. Use Model::getUserActions instead', E_USER_DEPRECATED);

        return $this->getUserActions(...func_get_args());
    }

    /**
     * @deprecated use hasUserAction instead - will be removed in dec-2020
     */
    public function hasAction($name): bool
    {
        'trigger_error'('Method Model::hasAction is deprecated. Use Model::hasUserAction instead', E_USER_DEPRECATED);

        return $this->hasUserAction(...func_get_args());
    }

    /**
     * @deprecated use getUserAction instead - will be removed in dec-2020
     */
    public function getAction($name): Model\UserAction
    {
        'trigger_error'('Method Model::getAction is deprecated. Use Model::getUserAction instead', E_USER_DEPRECATED);

        return $this->getUserAction(...func_get_args());
    }

    /**
     * @deprecated use executeUserAction instead - will be removed in dec-2020
     */
    public function executeAction($name, ...$args)
    {
        'trigger_error'('Method Model::executeAction is deprecated. Use Model::executeUserAction instead', E_USER_DEPRECATED);

        $this->executeUserAction(...func_get_args());
    }

    /**
     * @deprecated use removeUserAction instead - will be removed in dec-2020
     */
    public function removeAction($name)
    {
        'trigger_error'('Method Model::removeAction is deprecated. Use Model::removeUserAction instead', E_USER_DEPRECATED);

        return $this->removeUserAction(...func_get_args());
    }
}
