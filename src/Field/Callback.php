<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\core\InitializerTrait;

/**
 * Evaluate php expression after load.
 */
class Callback extends \atk4\data\Field
{
    use InitializerTrait {
        init as _init;
    }

    /**
     * Method to execute for evaluation.
     *
     * @var mixed
     */
    public $expr = null;

    /**
     * Expressions are always read_only.
     *
     * @var bool
     */
    public $read_only = true;

    /**
     * Never persist this field.
     */
    public $never_persist = true;

    public function __construct($callback)
    {
        $this->expr = $callback;
    }

    /**
     * Initialization.
     */
    public function init()
    {
        $this->_init();

        $this->owner->addHook('afterLoad', function ($m) {
            $m->data[$this->short_name] = call_user_func($this->expr, $m);
        });
    }
}
