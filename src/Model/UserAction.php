<?php

declare(strict_types=1);

namespace atk4\data\Model;

use atk4\core\DiContainerTrait;
use atk4\core\Exception;
use atk4\core\InitializerTrait;
use atk4\core\TrackableTrait;
use atk4\data\Model;

/**
 * Implements generic user action. Assigned to a model it can be invoked by a user. Model\UserAction class contains a
 * meta information about the action (arguments, permissions, appliesTo records, etc) that will help UI/API or add-ons to display
 * action trigger (button) correctly in an automated way.
 *
 * UserAction must NOT rely on any specific UI implementation.
 *
 * @property Model $owner
 */
class UserAction
{
    use DiContainerTrait;
    use TrackableTrait;
    use InitializerTrait {
        init as init_;
    }

    /** Defining records scope of the action */
    public const APPLIES_TO_NO_RECORDS = 'none'; // e.g. add
    public const APPLIES_TO_SINGLE_RECORD = 'single'; // e.g. archive
    public const APPLIES_TO_MULTIPLE_RECORDS = 'multiple'; // e.g. delete
    public const APPLIES_TO_ALL_RECORDS = 'all'; // e.g. truncate

    // deprecated constants - will be removed in dec-2020
    public const NO_RECORDS = self::APPLIES_TO_NO_RECORDS;
    public const SINGLE_RECORD = self::APPLIES_TO_SINGLE_RECORD;
    public const MULTIPLE_RECORDS = self::APPLIES_TO_MULTIPLE_RECORDS;
    public const ALL_RECORDS = self::APPLIES_TO_ALL_RECORDS;

    /** @deprecated use appliesTo instead - will be removed in dec-2020 */
    public $scope;

    /** @var string by default - action is for a single-record */
    public $appliesTo = self::APPLIES_TO_SINGLE_RECORD;

    /** Defining action modifier */
    public const MODIFIER_CREATE = 'create'; // create new record(s).
    public const MODIFIER_UPDATE = 'update'; // update existing record(s).
    public const MODIFIER_DELETE = 'delete'; // delete record(s).
    public const MODIFIER_READ = 'read'; // just read, does not modify record(s).

    /** @var string How this action interact with record. default = 'read' */
    public $modifier = self::MODIFIER_READ;

    /** @var \Closure code to execute. By default will call method with same name */
    public $callback;

    /** @var \Closure code, identical to callback, but would generate preview of action without permanent effect */
    public $preview;

    /** @var string caption to put on the button */
    public $caption;

    /** @var string a longer description of this action */
    public $description;

    /** @var bool Specifies that the action is dangerous. Should be displayed in red. */
    public $dangerous = false;

    /** @var bool|string|\Closure Set this to "true", string or return the value from the callback. Will ask user to confirm. */
    public $confirmation = false;

    /** @var array UI properties, e,g. 'icon'=>.. , 'warning', etc. UI implementation can interpret or extend. */
    public $ui = [];

    /** @var bool|\Closure setting this to false will disable action. Callback will be executed with ($m) and must return bool */
    public $enabled = true;

    /** @var bool system action will be hidden from UI, but can still be explicitly triggered */
    public $system = false;

    /** @var array Argument definition. */
    public $args = [];

    /** @var array|bool Specify which fields may be dirty when invoking action. APPLIES_TO_NO_RECORDS|APPLIES_TO_SINGLE_RECORD scopes for adding/modifying */
    public $fields = [];

    /** @var bool Atomic action will automatically begin transaction before and commit it after completing. */
    public $atomic = true;

    protected function init(): void
    {
        $this->init_();

        if ($this->scope) {
            'trigger_error'('Property UserAction::$scope is deprecated. Use Model::$appliesTo instead', E_USER_DEPRECATED);

            $this->appliesTo = $this->scope;
        }
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
        // todo - ACL tests must allow
        try {
            $this->validateBeforeExecute();

            $run = function () use ($args) {
                if ($this->callback === null) {
                    $fx = [$this->owner, $this->short_name];
                } elseif (is_string($this->callback)) {
                    $fx = [$this->owner, $this->callback];
                } else {
                    array_unshift($args, $this->owner);
                    $fx = $this->callback;
                }

                return $fx(...$args);
            };

            if ($this->atomic) {
                return $this->owner->atomic($run);
            }

            return $run();
        } catch (Exception $e) {
            $e->addMoreInfo('action', $this);

            throw $e;
        }
    }

    protected function validateBeforeExecute(): void
    {
        if ($this->enabled === false || ($this->enabled instanceof \Closure && ($this->enabled)() === false)) {
            throw new Exception('This action is disabled');
        }

        // Verify that model fields wouldn't be too dirty
        if (is_array($this->fields)) {
            $tooDirty = array_diff(array_keys($this->owner->dirty), $this->fields);

            if ($tooDirty) {
                throw (new Exception('Calling user action on a Model with dirty fields that are not allowed by this action.'))
                    ->addMoreInfo('too_dirty', $tooDirty)
                    ->addMoreInfo('dirty', array_keys($this->owner->dirty))
                    ->addMoreInfo('permitted', $this->fields);
            }
        } elseif (!is_bool($this->fields)) {
            throw (new Exception('Argument `fields` for the user action must be either array or boolean.'))
                ->addMoreInfo('fields', $this->fields);
        }

        // Verify some records scope cases
        switch ($this->appliesTo) {
            case self::APPLIES_TO_NO_RECORDS:
                if ($this->owner->loaded()) {
                    throw (new Exception('This user action can be executed on non-existing record only.'))
                        ->addMoreInfo('id', $this->owner->getId());
                }

                break;
            case self::APPLIES_TO_SINGLE_RECORD:
                if (!$this->owner->loaded()) {
                    throw new Exception('This user action requires you to load existing record first.');
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
            $fx = \Closure::fromCallable([$this->owner, $this->preview]);
        } else {
            array_unshift($args, $this->owner);
            $fx = $this->preview;
        }

        return $fx(...$args);
    }

    /**
     * Get description of this current action in a user-understandable language.
     */
    public function getDescription(): string
    {
        return $this->description ?? ('Will execute ' . $this->caption);
    }

    /**
     * Return confirmation message for action.
     */
    public function getConfirmation()
    {
        $confirmation = $this->confirmation;

        if ($confirmation instanceof \Closure) {
            $confirmation = $confirmation($this);
        }

        if ($confirmation === true) {
            $confirmation = 'Are you sure you wish to ' . $this->caption . ' ' . $this->owner->getTitle() . '?';
        }

        return $confirmation;
    }

    /**
     * Return model associate with this action.
     */
    public function getModel(): Model
    {
        return $this->owner;
    }
}
