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
 * @method Exception getOwner() use getModel() or getEntity() method instead
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

    /** @var string by default action is for a single record */
    public $appliesTo = self::APPLIES_TO_SINGLE_RECORD;

    /** Defining action modifier */
    public const MODIFIER_CREATE = 'create'; // create new record(s)
    public const MODIFIER_UPDATE = 'update'; // update existing record(s)
    public const MODIFIER_DELETE = 'delete'; // delete record(s)
    public const MODIFIER_READ = 'read'; // just read, does not modify record(s)

    /** @var string How this action interact with record */
    public $modifier;

    /** @var \Closure(object, mixed ...$args): mixed|string code to execute. By default will call entity method with same name */
    public $callback;

    /** @var \Closure(object, mixed ...$args): mixed|string identical to callback, but would generate preview of action without permanent effect */
    public $preview;

    /** @var string|null caption to put on the button */
    public $caption;

    /** @var string|\Closure(static): string|null a longer description of this action. */
    public $description;

    /** @var bool|string|\Closure(static): string Will ask user to confirm. */
    public $confirmation = false;

    /** @var bool|\Closure(object): bool setting this to false will disable action. */
    public $enabled = true;

    /** @var bool system action will be hidden from UI, but can still be explicitly triggered */
    public $system = false;

    /** @var array Argument definition. */
    public $args = [];

    /** @var array|bool Specify which fields may be dirty when invoking action. APPLIES_TO_NO_RECORDS|APPLIES_TO_SINGLE_RECORD scopes for adding/modifying */
    public $fields = [];

    /** @var bool Atomic action will automatically begin transaction before and commit it after completing. */
    public $atomic = true;

    public function isOwnerEntity(): bool
    {
        /** @var Model */
        $owner = $this->getOwner();

        return $owner->isEntity();
    }

    public function getModel(): Model
    {
        /** @var Model */
        $owner = $this->getOwner();

        return $owner->getModel(true);
    }

    public function getEntity(): Model
    {
        /** @var Model */
        $owner = $this->getOwner();
        $owner->assertIsEntity();

        return $owner;
    }

    /**
     * @return static
     */
    public function getActionForEntity(Model $entity): self
    {
        /** @var Model */
        $owner = $this->getOwner();

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
        if ($this->callback === null) {
            $fx = \Closure::fromCallable([$this->getEntity(), $this->shortName]);
        } elseif (is_string($this->callback)) {
            $fx = \Closure::fromCallable([$this->getEntity(), $this->callback]);
        } else {
            array_unshift($args, $this->getEntity());
            $fx = $this->callback;
        }

        // todo - ACL tests must allow

        try {
            $this->validateBeforeExecute();

            return $this->atomic === false
                ? $fx(...$args)
                : $this->getModel()->atomic(static fn () => $fx(...$args));
        } catch (CoreException $e) {
            $e->addMoreInfo('action', $this);

            throw $e;
        }
    }

    protected function validateBeforeExecute(): void
    {
        if ($this->enabled === false || ($this->enabled instanceof \Closure && ($this->enabled)($this->getEntity()) === false)) {
            throw new Exception('This action is disabled');
        }

        // Verify that model fields wouldn't be too dirty
        if (is_array($this->fields)) {
            $tooDirty = array_diff(array_keys($this->getEntity()->getDirtyRef()), $this->fields);

            if ($tooDirty) {
                throw (new Exception('Calling user action on a Model with dirty fields that are not allowed by this action'))
                    ->addMoreInfo('too_dirty', $tooDirty)
                    ->addMoreInfo('dirty', array_keys($this->getEntity()->getDirtyRef()))
                    ->addMoreInfo('permitted', $this->fields);
            }
        } elseif (!is_bool($this->fields)) {
            throw (new Exception('Argument `fields` for the user action must be either array or boolean'))
                ->addMoreInfo('fields', $this->fields);
        }

        // Verify some records scope cases
        switch ($this->appliesTo) {
            case self::APPLIES_TO_NO_RECORDS:
                if ($this->getEntity()->isLoaded()) {
                    throw (new Exception('This user action can be executed on non-existing record only'))
                        ->addMoreInfo('id', $this->getEntity()->getId());
                }

                break;
            case self::APPLIES_TO_SINGLE_RECORD:
                if (!$this->getEntity()->isLoaded()) {
                    throw new Exception('This user action requires you to load existing record first');
                }

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
        if ($this->preview === null) {
            throw new Exception('You must specify preview callback explicitly');
        } elseif (is_string($this->preview)) {
            $fx = \Closure::fromCallable([$this->getEntity(), $this->preview]);
        } else {
            array_unshift($args, $this->getEntity());
            $fx = $this->preview;
        }

        return $fx(...$args);
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
                . ($this->getEntity()->getTitle() ? ' using ' . $this->getEntity()->getTitle() : '')
                . '?';

            return $confirmation;
        }

        return $this->confirmation;
    }

    public function getCaption(): string
    {
        return $this->caption ?? ucwords(str_replace('_', ' ', $this->shortName));
    }
}
