<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\UserAction;

use atk4\core\DIContainerTrait;
use atk4\core\Exception;
use atk4\core\InitializerTrait;
use atk4\core\TrackableTrait;

/**
 * Implements generic user action. Assigned to a model it can be invoked by a user. Action describes meta information about
 * the action that will help UI/API or add-ons to display action trigger (button) correctly, ensure that arguments
 * are provided.
 *
 * Action must NOT rely on any specific UI implementation.
 */
class Generic
{
    use DIContainerTrait;
    use TrackableTrait;
    use InitializerTrait {
        init as init_;
    }

    /** Defining scope of the action */
    const NO_RECORDS = 'none'; // e.g. add
    const SINGLE_RECORD = 'single'; // e.g. edit
    const MULTIPLE_RECORDS = 'multiple'; // e.g. delete
    const ALL_RECORDS = 'all'; // e.g. truncate

    /** @var string by default - action is for a single-record */
    public $scope = self::SINGLE_RECORD;

    /** @var callable code to execute. By default will call method with same name */
    public $callback = null;

    /** @var callable code, identical to callback, but would generate preview of action without permanent effect */
    public $preview = null;

    /** @var string caption to put on the button */
    public $caption = null;

    /** @var string a longer description of this action */
    public $description = null;

    /** @var array UI properties, e,g. 'icon'=>.. , 'warning', etc. UI implementation can interpret or extend. */
    public $ui = [];

    /** @var bool|callable setting this to false will disable action. Callback will be executed with ($m) and must return bool */
    public $enabled = true;

    /** @var bool system action will be hidden from UI, but can still be explicitly triggered */
    public $system = false;

    /** @var array Argument definition. */
    public $args = [];

    /** @var bool Atomic action will automatically begin transaction before and commit it after completing. */
    public $atomic = true;

    public function init()
    {
        $this->init_();
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
        // todo - assert owner model loaded

        // todo - start transaction, if atomic

        // todo - pass model as first argument ?

        // todo - ACL tests must allow

        if ($this->callback === null) {
            $cb = [$this->owner, substr($this->short_name, strlen('action:'))];
        } elseif (is_string($this->callback)) {
            $cb = [$this->owner, $this->callback];
        } else {
            array_unshift($args, $this->owner);
            $cb = $this->callback;
        }
        
        return call_user_func_array($cb, $args);
    }

    /**
     * Identical to Execute but display a preview of what will happen.
     *
     * @param mixed ...$args
     *
     * @throws Exception
     *
     * @return mixed
     */
    public function preview(...$args)
    {
        if ($this->preview === null) {
            throw new Exception(['You must specify preview callback explicitly']);
        } elseif (is_string($this->preview)) {
            $cb = [$this->owner, $this->preview];
        } else {
            array_unshift($args, $this->owner);
            $cb = $this->preview;
        }
        
        return call_user_func_array($cb, $args);
    }

    /**
     * Get description of this current action in a user-understandable language.
     *
     * @return string
     */
    public function getDescription()
    {
        return $this->description ?? ('Will execute '.$this->caption);
    }
}
