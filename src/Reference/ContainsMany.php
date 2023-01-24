<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class ContainsMany extends ContainsBase
{
    protected function getDefaultPersistence(Model $theirModel): Persistence
    {
        $ourModel = $this->getOurModelPassedToRefXxx();

        return new Persistence\Array_([
            $this->tableAlias => $ourModel->isEntity() && $this->getOurFieldValue($ourModel) !== null ? $this->getOurFieldValue($ourModel) : [],
        ]);
    }

    public function ref(Model $ourModel, array $defaults = []): Model
    {
        $ourModel = $this->getOurModel($ourModel);

        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'containedInEntity' => $ourModel->isEntity() ? $ourModel : null,
            'table' => $this->tableAlias,
        ]));

        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirEntity) {
                $ourModel = $this->getOurModel($theirEntity->containedInEntity);
                $ourModel->assertIsEntity();

                /** @var Persistence\Array_ */
                $persistence = $theirEntity->getModel()->getPersistence();
                $rows = $persistence->getRawDataByTable($theirEntity->getModel(), $this->tableAlias); // @phpstan-ignore-line
                $ourModel->save([$this->getOurFieldName() => $rows !== [] ? $rows : null]);
            });
        }

        return $theirModel;
    }
}
