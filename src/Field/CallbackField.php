<?php

declare(strict_types=1);

namespace Atk4\Data\Field;

use Atk4\Core\InitializerTrait;
use Atk4\Data\Field;
use Atk4\Data\Model;

/**
 * Evaluate php expression after load.
 */
class CallbackField extends Field
{
    use InitializerTrait {
        init as private _init;
    }

    /** @var bool Expressions are always readOnly. */
    public $readOnly = true;
    /** @var bool Never persist this field. */
    public $neverPersist = true;

    /** @var \Closure(Model): mixed */
    public $expr;

    protected function init(): void
    {
        $this->_init();

        $this->ui['table']['sortable'] = false;

        $this->onHookToOwnerEntity(Model::HOOK_AFTER_LOAD, function (Model $entity) {
            $entity->getDataRef()[$this->shortName] = ($this->expr)($entity);
        });
    }
}
