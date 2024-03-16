<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Reference;

abstract class ContainsBase extends Reference
{
    public bool $checkTheirType = false;

    /** Field type. */
    public string $type = 'json';

    /** Is it system field? */
    public bool $system = true;

    /** @var array<string, mixed> Array with UI flags like editable, visible and hidden. */
    public array $ui = [];

    /** @var string Required! We need table alias for internal use only. */
    protected $tableAlias = 'tbl';

    #[\Override]
    protected function init(): void
    {
        parent::init();

        if ($this->ourField === null) {
            $this->ourField = $this->link;
        }

        $ourModel = $this->getOurModel();

        $ourField = $this->getOurFieldName();
        if (!$ourModel->hasField($ourField)) {
            $ourModel->addField($ourField, [
                'type' => $this->type,
                'referenceLink' => $this->link,
                'system' => $this->system,
                'caption' => $this->caption, // it's reference models caption, but we can use it here for field too
                'ui' => array_merge([
                    'visible' => false, // not visible in UI Table, Grid and Crud
                    'editable' => true, // but should be editable in UI Form
                ], $this->ui),
            ]);
        }

        // TODO https://github.com/atk4/data/issues/881
        // prevent unmanaged ContainsXxx data modification (/wo proper normalize, hooks, ...)
        $this->onHookToOurModel(Model::HOOK_NORMALIZE, function (Model $ourModel, Field $field, $value) {
            if (!$field->hasReference() || $field->shortName !== $this->getOurFieldName() || $value === null) {
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

    #[\Override]
    protected function getDefaultPersistence(): Persistence
    {
        return new Persistence\Array_();
    }

    /**
     * @param array<int, mixed> $data
     */
    protected function setTheirModelPersistenceSeedData(Model $theirModel, array $data): void
    {
        $persistence = Persistence\Array_::assertInstanceOf($theirModel->getPersistence());
        $tableName = $this->tableAlias;
        \Closure::bind(static function () use ($persistence, $tableName, $data) {
            $persistence->seedData = [$tableName => $data];
            $persistence->data = [];
        }, null, Persistence\Array_::class)();
    }
}
