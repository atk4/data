<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Core\DiContainerTrait;
use Atk4\Core\Exception as CoreException;
use Atk4\Core\InitializerTrait;
use Atk4\Core\TrackableTrait;
use Atk4\Data\Exception;
use Atk4\Data\Model;

/**
 * Implements generic user action. Assigned to a model it can be invoked by a user. Model\UserAction class contains a
 * meta information about the action (arguments, permissions, appliesTo records, etc) that will help UI/API or add-ons to display
 * action trigger (button) correctly in an automated way.
 *
 * UserAction must NOT rely on any specific UI implementation.
 *
 * @method false getOwner() use getModel() or getEntity() method instead
 */
class UserAction
{
    use DiContainerTrait;
    use InitializerTrait;
    use TrackableTrait;

    /** Defining records scope of the action */
    public const APPLIES_TO_NO_RECORDS = 'none'; // e.g. add
    public const APPLIES_TO_SINGLE_RECORD = 'single'; // e.g. archive
    public const APPLIES_TO_MULTIPLE_RECORDS = 'multiple'; // e.g. delete
    public const APPLIES_TO_ALL_RECORDS = 'all'; // e.g. truncate

    /** Defining action modifier */
    public const MODIFIER_CREATE = 'create'; // create new record(s)
    public const MODIFIER_UPDATE = 'update'; // update existing record(s)
    public const MODIFIER_DELETE = 'delete'; // delete record(s)
    public const MODIFIER_READ = 'read'; // just read, does not modify record(s)

    /** @var string by default action is for a single record */
    public $appliesTo = self::APPLIES_TO_SINGLE_RECORD;

    /** @var string How this action interact with record */
    public $modifier;

    /** @var \Closure(object, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed|string code to execute. By default will call entity method with same name */
    public $callback;

    /** @var \Closure(object, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed, mixed): mixed|string identical to callback, but would generate preview of action without permanent effect */
    public $preview;

    /** @var string|null caption to put on the button */
    public $caption;

    /** @var string|\Closure($this): string|null a longer description of this action. */
    public $description;

    /** @var bool|string|\Closure($this): string Will ask user to confirm. */
    public $confirmation = false;

    /** @var bool|\Closure(object): bool setting this to false will disable action. */
    public $enabled = true;

    /** @var bool system action will be hidden from UI, but can still be explicitly triggered */
    public $system = false;

    /** @var array<string, array<string, mixed>|Model> Argument definition. */
    public $args = [];

    /** @var array<int, string>|bool Specify which fields may be dirty when invoking action. APPLIES_TO_NO_RECORDS|APPLIES_TO_SINGLE_RECORD scopes for adding/modifying */
    public $fields = [];

    /** @var bool Atomic action will automatically begin transaction before and commit it after completing. */
    public $atomic = true;

    private function _getOwner(): Model
    {
        return $this->getOwner(); // @phpstan-ignore-line;
    }

    public function isOwnerEntity(): bool
    {
        $owner = $this->_getOwner();

        return $owner->isEntity();
    }

    public function getModel(): Model
    {
        $owner = $this->_getOwner();

        return $owner->getModel(true);
    }

    public function getEntity(): Model
    {
        $owner = $this->_getOwner();
        $owner->assertIsEntity();

        return $owner;
    }

    /**
     * @return static
     */
    public function getActionForEntity(Model $entity): self
    {
        $owner = $this->_getOwner();

        $entity->assertIsEntity($owner);
        foreach ($owner->getUserActions() as $name => $action) {
            if ($action === $this) {
                return $entity->getUserAction($name); // @phpstan-ignore-line
            }
        }

        throw new Exception('Action instance not found in model');
    }

    /**
     * Attempt to execute callback of the action.
     *
     * @param mixed ...$args
     *
     * @return mixed
     */
    public function execute(...$args)
    {
        $passOwner = false;
        if ($this->callback === null) {
            $fx = \Closure::fromCallable([$this->_getOwner(), $this->shortName]);
        } elseif (is_string($this->callback)) {
            $fx = \Closure::fromCallable([$this->_getOwner(), $this->callback]);
        } else {
            $passOwner = true;
            $fx = $this->callback;
        }

        // todo - ACL tests must allow

        try {
            $this->validateBeforeExecute();

            if ($passOwner) {
                array_unshift($args, $this->_getOwner());
            }

            return $this->atomic === false
                ? $fx(...$args)
                : $this->_getOwner()->atomic(static fn () => $fx(...$args));
        } catch (CoreException $e) {
            $e->addMoreInfo('action', $this);

            throw $e;
        }
    }

    protected function validateBeforeExecute(): void
    {
        if ($this->enabled === false || ($this->enabled instanceof \Closure && ($this->enabled)($this->_getOwner()) === false)) {
            throw new Exception('User action is disabled');
        }

        if (!is_bool($this->fields) && $this->isOwnerEntity()) {
            $dirtyFields = array_keys($this->getEntity()->getDirtyRef());
            $tooDirtyFields = array_diff($dirtyFields, $this->fields);

            if ($tooDirtyFields !== []) {
                throw (new Exception('User action cannot be executed when unrelated fields are dirty'))
                    ->addMoreInfo('tooDirtyFields', $tooDirtyFields)
                    ->addMoreInfo('otherDirtyFields', array_diff($dirtyFields, $tooDirtyFields));
            }
        }

        switch ($this->appliesTo) {
            case self::APPLIES_TO_NO_RECORDS:
                if ($this->getEntity()->isLoaded()) {
                    throw (new Exception('User action can be executed on new entity only'))
                        ->addMoreInfo('id', $this->getEntity()->getId());
                }

                break;
            case self::APPLIES_TO_SINGLE_RECORD:
                if (!$this->getEntity()->isLoaded()) {
                    throw new Exception('User action can be executed on loaded entity only');
                }

                break;
            case self::APPLIES_TO_MULTIPLE_RECORDS:
            case self::APPLIES_TO_ALL_RECORDS:
                $this->_getOwner()->assertIsModel();

                break;
        }
    }

    /**
     * Identical to Execute but display a preview of what will happen.
     *
     * @param mixed ...$args
     *
     * @return mixed
     */
    public function preview(...$args)
    {
        $passOwner = false;
        if (is_string($this->preview)) {
            $fx = \Closure::fromCallable([$this->_getOwner(), $this->preview]);
        } else {
            $passOwner = true;
            $fx = $this->preview;
        }

        try {
            $this->validateBeforeExecute();

            if ($passOwner) {
                array_unshift($args, $this->_getOwner());
            }

            return $fx(...$args);
        } catch (CoreException $e) {
            $e->addMoreInfo('action', $this);

            throw $e;
        }
    }

    /**
     * Get description of this current action in a user-understandable language.
     */
    public function getDescription(): string
    {
        if ($this->description instanceof \Closure) {
            return ($this->description)($this);
        }

        return $this->description ?? $this->getCaption() . ' ' . $this->getModel()->getModelCaption();
    }

    /**
     * Return confirmation message for action.
     *
     * @return string|false
     */
    public function getConfirmation()
    {
        if ($this->confirmation instanceof \Closure) {
            return ($this->confirmation)($this);
        } elseif ($this->confirmation === true) {
            $confirmation = 'Are you sure you wish to execute '
                . $this->getCaption()
                . ($this->isOwnerEntity() && $this->getEntity()->getTitle() ? ' using ' . $this->getEntity()->getTitle() : '')
                . '?';

            return $confirmation;
        }

        return $this->confirmation;
    }

    public function getCaption(): string
    {
        return $this->caption ?? $this->getModel()->readableCaption($this->shortName);
    }
}
