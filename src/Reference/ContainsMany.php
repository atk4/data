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

        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'containedInEntity' => $ourModel->isEntity() ? $ourModel : null,
            'table' => $this->table_alias,
        ]));

        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirModel) {
                $ourModel = $this->getOurModel($theirModel->containedInEntity);
                $ourModel->assertIsEntity();

                /** @var Persistence\Array_ */
                $persistence = $theirModel->persistence;
                $rows = $persistence->getRawDataByTable($theirModel, $this->table_alias);
                $ourModel->save([$this->getOurFieldName() => $rows ?: null]);
            });
        }

        return $theirModel;
    }
}
