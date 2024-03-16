<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class ContainsMany extends ContainsBase
{
    #[\Override]
    public function ref(Model $ourModelOrEntity, array $defaults = []): Model
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'containedInEntity' => $ourModelOrEntity->isEntity() ? $ourModelOrEntity : null,
            'table' => $this->tableAlias,
        ]));

        $this->setTheirModelPersistenceSeedData(
            $theirModel,
            $ourModelOrEntity->isEntity() && $this->getOurFieldValue($ourModelOrEntity) !== null
                ? $this->getOurFieldValue($ourModelOrEntity)
                : []
        );

        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirEntity) {
                $ourEntity = $theirEntity->getModel()->containedInEntity;
                $this->assertOurModelOrEntity($ourEntity);
                $ourEntity->assertIsEntity();

                $persistence = Persistence\Array_::assertInstanceOf($theirEntity->getModel()->getPersistence());
                $rows = $persistence->getRawDataByTable($theirEntity->getModel(), $this->tableAlias); // @phpstan-ignore-line
                $ourEntity->save([$this->getOurFieldName() => $rows !== [] ? $rows : null]);
            });
        }

        return $theirModel;
    }
}
