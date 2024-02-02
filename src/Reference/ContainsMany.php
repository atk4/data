<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class ContainsMany extends ContainsBase
{
    #[\Override]
    protected function getDefaultPersistence(Model $theirModel): Persistence
    {
        $ourModelOrEntity = $this->getOurModelOrEntityPassedToRefXxx();

        return new Persistence\Array_([
            $this->tableAlias => $ourModelOrEntity->isEntity() && $this->getOurFieldValue($ourModelOrEntity) !== null
                ? $this->getOurFieldValue($ourModelOrEntity)
                : [],
        ]);
    }

    #[\Override]
    public function ref(Model $ourModelOrEntity, array $defaults = []): Model
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'containedInEntity' => $ourModelOrEntity->isEntity() ? $ourModelOrEntity : null,
            'table' => $this->tableAlias,
        ]));

        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirEntity) {
                $ourEntity = $theirEntity->containedInEntity;
                $this->assertOurModelOrEntity($ourEntity);
                $ourEntity->assertIsEntity();

                /** @var Persistence\Array_ */
                $persistence = $theirEntity->getModel()->getPersistence();
                $rows = $persistence->getRawDataByTable($theirEntity->getModel(), $this->tableAlias); // @phpstan-ignore-line
                $ourEntity->save([$this->getOurFieldName() => $rows !== [] ? $rows : null]);
            });
        }

        return $theirModel;
    }
}
