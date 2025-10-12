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
    protected const EARLY_HOOK_PRIORITY = -500;

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

        // prevent unmanaged data modification
        // https://github.com/atk4/data/issues/881
        $this->onHookToOurModel(Model::HOOK_NORMALIZE, function (Model $ourModel, Field $field, $value) {
            if (!$field->hasReference() || $field->shortName !== $this->getOurFieldName()) {
                return;
            }
            assert($field->getReference() === $this);

            $calledFromModelSet = false;
            foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
                if (!$calledFromModelSet) {
                    if ($frame['function'] === 'set' && ($frame['object'] ?? null) instanceof Model && $frame['object']->getModel() === $this->getOurModel()) {
                        $calledFromModelSet = true;
                    }
                } else {
                    // allow save from ContainsOne hooks
                    if (($frame['object'] ?? null) === $this) {
                        return;
                    }
                }
            }

            if ($calledFromModelSet) {
                throw new Exception('Contained model data cannot be modified directly');
            }
        }, [], \PHP_INT_MIN);

        $this->onHookToOurModel(Model::HOOK_BEFORE_DELETE, function (Model $ourEntity) {
            $this->deleteTheirEntities($ourEntity);
        });
    }

    #[\Override]
    protected function getDefaultPersistence(): Persistence
    {
        return new Persistence\Array_();
    }

    #[\Override]
    protected function createTheirModelBeforeInit(array $defaults): Model
    {
        $defaults['table'] = $this->tableAlias;

        $defaults['containedInPersistence'] ??= $this->getOwner()->containedInPersistence
            ?? $this->getOwner()->getPersistence();

        return parent::createTheirModelBeforeInit($defaults);
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

    protected function deleteTheirEntities(Model $ourEntity): void
    {
        $theirModelOrEntity = $this->ref($ourEntity);

        if ($theirModelOrEntity->isEntity()) {
            // ContainsOne::ref() method returns an unloaded entity when traversing entity is not found
            // https://github.com/atk4/data/blob/6.0.0/src/Reference/ContainsOne.php#L47
            if (!$theirModelOrEntity->isLoaded()) {
                $theirModelOrEntity = [];
            } else {
                $theirModelOrEntity = [$theirModelOrEntity];
            }
        }

        foreach ($theirModelOrEntity as $theirEntity) {
            $theirEntity->delete();
        }
    }
}
