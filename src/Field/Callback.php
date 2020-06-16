<?php

declare(strict_types=1);

namespace atk4\data\Field;

use atk4\core\InitializerTrait;
use atk4\data\Model;

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
    public $expr;

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

    /**
     * Initialization.
     */
    public function init(): void
    {
        $this->_init();

        $this->ui['table']['sortable'] = false;

        $this->owner->onHook(Model::HOOK_AFTER_LOAD, function ($m) {
            $m->data[$this->short_name] = call_user_func($this->expr, $m);
        });
    }
}
