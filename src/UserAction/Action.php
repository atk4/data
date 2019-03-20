<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\UserAction;

use atk4\core\TrackableTrait;

/**
 * Implements generic user action. Assigned to a model it can be invoked by a user. Action describes meta information about
 * the action that will help UI/API or add-ons to display action trigger (button) correctly, ensure that arguments
 * are provided.
 *
 * Action must NOT rely on any specific UI implementation.
 */
class Action
{
    use TrackableTrait;

    /** Defining scope of the action */
    const SINGLE_RECORD = 1; // e.g. edit
    const ALL_RECORDS = 2; // e.g. truncate
    const MULTIPLE_RECORDS = 3; // e.g. delete
    const NO_RECORDS = 4; // e.g. add

    /** @var int by default - action is for a single-record */
    public $scope = self::SINGLE_RECORD;

    /** @var callable code to execute. By default will call method with same name */
    public $callback = null;

    /** @var string caption to put on the button */
    public $caption = null;

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

    public function execute(...$args)
    {
        // todo - assert owner model loaded

        // todo - start transaction, if atomic

        // todo - pass model as first argument ?

        $callback = $this->callback ?: [$this->owner, str_replace('action:', '', $this->short_name)];

        return call_user_func_array($callback, $args);
    }
}
