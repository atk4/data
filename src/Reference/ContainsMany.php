<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * ContainsMany reference.
 */
class ContainsMany extends ContainsOne
{
    use ContainsSeedHackTrait;

    protected function getDefaultPersistence(Model $theirModel): Persistence
    {
        $ourModel = $this->getOurModelPassedToRefXxx();

        return new Persistence\Array_([
            $this->table_alias => $ourModel->isEntity() && $this->getOurFieldValue($ourModel) !== null ? $this->getOurFieldValue($ourModel) : [],
        ]);
    }

    /**
     * Returns referenced model.
     */
    public function ref(Model $ourModel, array $defaults = []): Model
    {
        $ourModel = $this->getOurModel($ourModel);

        // get model
        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'contained_in_root_model' => $ourModel->contained_in_root_model ?: $ourModel,
            'table' => $this->table_alias,
        ]));

        // set some hooks for ref_model
        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirModel) use ($ourModel) {
                /** @var Persistence\Array_ */
                $persistence = $theirModel->persistence;
                $rows = $persistence->getRawDataByTable($theirModel, $this->table_alias);
                $this->getOurModel($ourModel)->save([
                    $this->getOurFieldName() => $rows ?: null,
                ]);
            });
        }

        return $theirModel;
    }
}
