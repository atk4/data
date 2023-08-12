<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class ContainsOne extends ContainsBase
{
    protected function getDefaultPersistence(Model $theirModel): Persistence
    {
        $ourModel = $this->getOurModelPassedToRefXxx();

        return new Persistence\Array_([
            $this->tableAlias => $ourModel->isEntity() && $this->getOurFieldValue($ourModel) !== null ? [1 => $this->getOurFieldValue($ourModel)] : [],
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
                $row = $persistence->getRawDataByTable($theirEntity->getModel(), $this->tableAlias); // @phpstan-ignore-line
                $row = $row ? array_shift($row) : null; // get first and only one record from array persistence
                $ourModel->save([$this->getOurFieldName() => $row]);
            });
        }

        if ($ourModel->isEntity()) {
            $theirModelOrig = $theirModel;
            $theirModel = $theirModel->tryLoadOne();

            if ($theirModel === null) {
                $theirModel = $theirModelOrig->createEntity();
            }
        }

        return $theirModel;
    }
}
