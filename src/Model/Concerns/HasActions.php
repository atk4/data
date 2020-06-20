<?php

declare(strict_types=1);

namespace atk4\data\Model\Concerns;

use atk4\data\Model;

trait HasActions
{
    /**
     * Default class for addAction().
     *
     * @var string|array
     */
    public $_default_seed_action = Model\Action::class;

    /**
     * Register new user action for this model. By default UI will allow users to trigger actions
     * from UI.
     *
     * @param string         $name     Action name
     * @param array|callable $defaults
     */
    public function addAction($name, $defaults = []): Model\Action
    {
        if (is_callable($defaults)) {
            $defaults = ['callback' => $defaults];
        }

        if (!isset($defaults['caption'])) {
            $defaults['caption'] = $this->readableCaption($name);
        }

        /** @var Model\Action $action */
        $action = $this->factory($this->_default_seed_action, $defaults);

        $this->_addIntoCollection($name, $action, 'actions');

        return $action;
    }

    /**
     * Returns list of actions for this model. Can filter actions by scope.
     * It will also skip system actions (where system === true).
     *
     * @param int $scope e.g. Model\Action::SCOPE_ALL
     */
    public function getActions($scope = null): array
    {
        return array_filter($this->actions, function ($action) use ($scope) {
            return !$action->system && ($scope === null || $action->scope === $scope);
        });
    }

    /**
     * Returns true if user action with a corresponding name exists.
     *
     * @param string $name Action name
     */
    public function hasAction($name): bool
    {
        return $this->_hasInCollection($name, 'actions');
    }

    /**
     * Returns one action object of this model. If action not defined, then throws exception.
     *
     * @param string $name Action name
     */
    public function getAction($name): Model\Action
    {
        return $this->_getFromCollection($name, 'actions');
    }

    /**
     * Execute specified action with specified arguments.
     *
     * @param string $name Action name
     */
    public function executeAction($name, ...$args)
    {
        $this->getAction($name)->execute(...$args);
    }

    /**
     * Remove specified action(s).
     *
     * @param string|array $name
     *
     * @return $this
     */
    public function removeAction($name)
    {
        foreach ((array) $name as $action) {
            $this->_removeFromCollection($action, 'actions');
        }

        return $this;
    }
}
