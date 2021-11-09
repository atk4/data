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

    /** @var bool Expressions are always read_only. */
    public $read_only = true;
    /** @var bool Never persist this field. */
    public $never_persist = true;

    /** @var \Closure(Model): mixed */
    public $expr;

    protected function init(): void
    {
        $this->_init();

        $this->ui['table']['sortable'] = false;

        $this->onHookToOwner(Model::HOOK_AFTER_LOAD, function (Model $entity) {
            $entity->assertIsEntity($this->getOwner());

            $entity->getDataRef()[$this->short_name] = ($this->expr)($entity);
        });
    }
}
