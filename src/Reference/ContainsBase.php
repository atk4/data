<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;

abstract class ContainsBase extends Reference
{
    use ContainsSeedHackTrait;

    /** @var string Field type. */
    public $type = 'json';

    /** @var bool Is it system field? */
    public $system = true;

    /**
     * Array with UI flags like editable, visible and hidden.
     *
     * By default hasOne relation ID field should be editable in forms,
     * but not visible in grids. UI should respect these flags.
     *
     * @var array
     */
    public $ui = [];

    /** @var string Required! We need table alias for internal use only. */
    protected $table_alias = 'tbl';

    /**
     * Reference\ContainsOne will also add a field corresponding
     * to 'our_field' unless it exists of course.
     */
    protected function init(): void
    {
        parent::init();

        if (!$this->our_field) {
            $this->our_field = $this->link;
        }

        $ourModel = $this->getOurModel(null);

        $ourField = $this->getOurFieldName();
        if (!$ourModel->hasField($ourField)) {
            $ourModel->addField($ourField, [
                'type' => $this->type,
                'referenceLink' => $this->link,
                'system' => $this->system,
                'caption' => $this->caption, // it's ref models caption, but we can use it here for field too
                'ui' => array_merge([
                    'visible' => false, // not visible in UI Table, Grid and Crud
                    'editable' => true, // but should be editable in UI Form
                ], $this->ui),
            ]);
        }

        // TODO https://github.com/atk4/data/issues/881
        // prevent unmanaged ContainsXxx data modification (/wo proper normalize, hooks, ...)
        $this->onHookToOurModel($ourModel, Model::HOOK_NORMALIZE, function (Model $ourModel, Field $field, $value) {
            $ourRef = $field->getReference();
            if ($ourRef === null || $field->shortName !== $this->getOurFieldName() || $value === null) {
                // this code relies on Field::$referenceLink set
                // also, allowing null value to be set will not fire any HOOK_BEFORE_DELETE/HOOK_AFTER_DELETE hook
                return;
            }

            foreach (array_slice(debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS), 1) as $frame) {
                if (($frame['class'] ?? null) === static::class) {
                    return; // allow load/save from ContainsOne hooks
                }
            }

            throw new Exception('ContainsXxx does not support unmanaged data modification');
        });
    }
}
