<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

/**
 * Evaluate php expression after load.
 */
class Callback extends \atk4\data\Field
{
    /**
     * Method to execute for evaluation.
     *
     * @var mixed
     */
    public $fx = null;

    /**
     * Method to execute for evaluation.
     *
     * @var mixed
     *
     * @deprecated use $fx instead
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
     *
     * @var bool
     */
    public $never_persist = true;

    protected static $seedProperties = [
        'fx',
        'expr',
    ];

    /**
     * Initialization.
     */
    public function init()
    {
        parent::init();

        $this->ui['table']['sortable'] = false;

        $this->owner->onHook('afterLoad', function ($model) {
            $model->data[$this->short_name] = call_user_func($this->fx ?: $this->expr, $model);
        });
    }
}
