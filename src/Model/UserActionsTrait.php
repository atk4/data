<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\Factory;
use Atk4\Data\Model;

trait UserActionsTrait
{
    /**
     * Default class for addUserAction().
     *
     * @var string|array
     */
    public $_default_seed_action = [Model\UserAction::class];

    /**
     * @var array<string, Model\UserAction> Collection of user actions - using key as action system name
     */
    protected $userActions = [];

    /**
     * Register new user action for this model. By default UI will allow users to trigger actions
     * from UI.
     *
     * @param string         $name     Action name
     * @param array|\Closure $defaults
     */
    public function addUserAction(string $name, $defaults = []): Model\UserAction
    {
        if ($defaults instanceof \Closure) {
            $defaults = ['callback' => $defaults];
        }

        if (!isset($defaults['caption'])) {
            $defaults['caption'] = $this->readableCaption($name);
        }

        /** @var Model\UserAction $action */
        $action = Factory::factory($this->_default_seed_action, $defaults);

        $this->_addIntoCollection($name, $action, 'userActions');

        return $action;
    }

    /**
     * Returns true if user action with a corresponding name exists.
     *
     * @param string $name UserAction name
     */
    public function hasUserAction(string $name): bool
    {
        return $this->_hasInCollection($name, 'userActions');
    }

    /**
     * Returns list of actions for this model. Can filter actions by records they apply to.
     * It will also skip system user actions (where system === true).
     *
     * @param string $appliesTo e.g. Model\UserAction::APPLIES_TO_ALL_RECORDS
     *
     * @return array<string, Model\UserAction>
     */
    public function getUserActions(string $appliesTo = null): array
    {
        return array_filter($this->userActions, function ($action) use ($appliesTo) {
            return !$action->system && ($appliesTo === null || $action->appliesTo === $appliesTo);
        });
    }

    /**
     * Returns one action object of this model. If action not defined, then throws exception.
     *
     * @param string $name Action name
     */
    public function getUserAction(string $name): Model\UserAction
    {
        return $this->_getFromCollection($name, 'userActions');
    }

    /**
     * Remove specified action(s).
     *
     * @return $this
     */
    public function removeUserAction(string $name)
    {
        foreach ((array) $name as $action) {
            $this->_removeFromCollection($action, 'userActions');
        }

        return $this;
    }

    /**
     * Execute specified action with specified arguments.
     *
     * @param string $name    UserAction name
     * @param mixed  ...$args
     *
     * @return mixed
     */
    public function executeUserAction(string $name, ...$args)
    {
        return $this->getUserAction($name)->execute(...$args);
    }

    protected function initUserActions(): void
    {
        // Declare our basic Crud actions for the model.
        $this->addUserAction('add', [
            'fields' => true,
            'modifier' => Model\UserAction::MODIFIER_CREATE,
            'appliesTo' => Model\UserAction::APPLIES_TO_NO_RECORDS,
            'callback' => 'save',
            'description' => 'Add ' . $this->getModelCaption(),
        ]);

        $this->addUserAction('edit', [
            'fields' => true,
            'modifier' => Model\UserAction::MODIFIER_UPDATE,
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'callback' => 'save',
        ]);

        $this->addUserAction('delete', [
            'appliesTo' => Model\UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => Model\UserAction::MODIFIER_DELETE,
            'callback' => function ($model) {
                return $model->delete();
            },
        ]);

        $this->addUserAction('validate', [
            //'appliesTo' => any!
            'description' => 'Provided with modified values will validate them but will not save',
            'modifier' => Model\UserAction::MODIFIER_READ,
            'fields' => true,
            'system' => true, // don't show by default
            'args' => ['intent' => 'string'],
        ]);
    }
}
