<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\Factory;
use Atk4\Data\Exception;

trait UserActionsTrait
{
    /**
     * Default class for addUserAction().
     *
     * @var string|array
     */
    public $_default_seed_action = [UserAction::class];

    /**
     * @var array<string, UserAction> Collection of user actions - using key as action system name
     */
    protected $userActions = [];

    /**
     * Register new user action for this model. By default UI will allow users to trigger actions
     * from UI.
     *
     * @param array|\Closure $defaults
     */
    public function addUserAction(string $name, $defaults = []): UserAction
    {
        if ($this->isEntity() && $this->getModel()->hasUserAction($name)) {
            $this->assertIsModel();
        }

        if ($defaults instanceof \Closure) {
            $defaults = ['callback' => $defaults];
        }

        if (!isset($defaults['caption'])) {
            $defaults['caption'] = $this->readableCaption($name);
        }

        /** @var UserAction $action */
        $action = Factory::factory($this->_default_seed_action, $defaults);

        $this->_addIntoCollection($name, $action, 'userActions');

        return $action;
    }

    /**
     * Returns true if user action with a corresponding name exists.
     */
    public function hasUserAction(string $name): bool
    {
        if ($this->isEntity() && $this->getModel()->hasUserAction($name)) {
            return true;
        }

        return $this->_hasInCollection($name, 'userActions');
    }

    private function addUserActionFromModel(string $name, UserAction $action): void
    {
        $this->assertIsEntity();
        $action->getOwner()->assertIsModel(); // @phpstan-ignore-line
        if (\Closure::bind(fn () => $action->entity, null, UserAction::class)() !== null) {
            throw new Exception('Model action entity is expected to be null');
        }

        // clone action and store it in entity
        $action = clone $action;
        $action->unsetOwner();
        $this->_addIntoCollection($name, $action, 'userActions');
    }

    /**
     * Returns list of actions for this model. Can filter actions by records they apply to.
     * It will also skip system user actions (where system === true).
     *
     * @param string $appliesTo e.g. UserAction::APPLIES_TO_ALL_RECORDS
     *
     * @return array<string, UserAction>
     */
    public function getUserActions(string $appliesTo = null): array
    {
        if ($this->isEntity()) {
            foreach (array_diff_key($this->getModel()->getUserActions($appliesTo), $this->userActions) as $name => $action) {
                $this->addUserActionFromModel($name, $action);
            }
        }

        return array_filter($this->userActions, function ($action) use ($appliesTo) {
            return !$action->system && ($appliesTo === null || $action->appliesTo === $appliesTo);
        });
    }

    /**
     * Returns one action object of this model. If action not defined, then throws exception.
     */
    public function getUserAction(string $name): UserAction
    {
        if ($this->isEntity() && !$this->_hasInCollection($name, 'userActions') && $this->getModel()->hasUserAction($name)) {
            $this->addUserActionFromModel($name, $this->getModel()->getUserAction($name));
        }

        return $this->_getFromCollection($name, 'userActions');
    }

    /**
     * Remove specified action(s).
     *
     * @return $this
     */
    public function removeUserAction(string $name)
    {
        if ($this->isEntity() && $this->getModel()->hasUserAction($name)) {
            $this->assertIsModel();
        }

        $this->_removeFromCollection($name, 'userActions');

        return $this;
    }

    /**
     * Execute specified action with specified arguments.
     *
     * @param mixed ...$args
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
            'modifier' => UserAction::MODIFIER_CREATE,
            'appliesTo' => UserAction::APPLIES_TO_NO_RECORDS,
            'callback' => 'save',
            'description' => 'Add ' . $this->getModelCaption(),
        ]);

        $this->addUserAction('edit', [
            'fields' => true,
            'modifier' => UserAction::MODIFIER_UPDATE,
            'appliesTo' => UserAction::APPLIES_TO_SINGLE_RECORD,
            'callback' => 'save',
        ]);

        $this->addUserAction('delete', [
            'appliesTo' => UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => UserAction::MODIFIER_DELETE,
            'callback' => function ($model) {
                return $model->delete();
            },
        ]);

        $this->addUserAction('validate', [
            //'appliesTo' => any!
            'description' => 'Provided with modified values will validate them but will not save',
            'modifier' => UserAction::MODIFIER_READ,
            'fields' => true,
            'system' => true, // don't show by default
            'args' => ['intent' => 'string'],
        ]);
    }
}
