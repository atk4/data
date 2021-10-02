<?php

declare(strict_types=1);

namespace Atk4\Data\Field;

use Atk4\Core\InitializerTrait;
use Atk4\Data\Model;

/**
 * Evaluate php expression after load.
 */
class Callback extends \Atk4\Data\Field
{
    use InitializerTrait {
        init as _init;
    }

    public $read_only = true;

    public $never_persist = true;

    /**
     * Method to execute for evaluation.
     *
     * @var \Closure
     */
    public $expr;

    protected function init(): void
    {
        $this->_init();

        $this->ui['table']['sortable'] = false;

        $this->onHookShortToOwner(Model::HOOK_AFTER_LOAD, function () {
            $model = $this->getOwner();

            $model->getDataRef()[$this->short_name] = ($this->expr)($model);
        });
    }
}
