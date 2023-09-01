<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\Exception as CoreException;
use Atk4\Core\Factory;
use Atk4\Data\Exception;
use Atk4\Data\Model;

trait UserActionsTrait
{
    /** @var array<mixed> The seed used by addUserAction() method. */
    protected $_defaultSeedUserAction = [UserAction::class];

    /** @var array<string, UserAction> Collection of user actions - using key as action system name */
    protected $userActions = [];

    /**
     * Register new user action for this model. By default UI will allow users to trigger actions
     * from UI.
     *
     * @template T of Model
     *
     * @param array<mixed>|\Closure(T, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed $seed
     */
    public function addUserAction(string $name, $seed = []): UserAction
    {
        $this->assertIsModel();

        if ($seed instanceof \Closure) {
            $seed = ['callback' => $seed];
        }

        $seed = Factory::mergeSeeds($seed, $this->_defaultSeedUserAction);
        $action = UserAction::fromSeed($seed);
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
        $this->assertIsModel();

        return array_filter($this->userActions, static function (UserAction $action) use ($appliesTo) {
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

        try {
            return $this->_getFromCollection($name, 'userActions');
        } catch (CoreException $e) {
            throw (new Exception('User action is not defined'))
                ->addMoreInfo('model', $this)
                ->addMoreInfo('userAction', $name);
        }
    }

    /**
     * Remove specified action.
     *
     * @return $this
     */
    public function removeUserAction(string $name)
    {
        $this->assertIsModel();

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
            'callback' => static function (Model $entity) {
                $entity->assertIsLoaded();

                return $entity->save();
            },
        ]);

        $this->addUserAction('delete', [
            'appliesTo' => UserAction::APPLIES_TO_SINGLE_RECORD,
            'modifier' => UserAction::MODIFIER_DELETE,
            'callback' => static function (Model $entity) {
                return $entity->delete();
            },
        ]);

        $this->addUserAction('validate', [
            // 'appliesTo' => any entity!
            'description' => 'Provided with modified values will validate them but will not save',
            'modifier' => UserAction::MODIFIER_READ,
            'fields' => true,
            'system' => true, // don't show by default
            'args' => [
                'intent' => ['type' => 'string'],
            ],
        ]);
    }
}
