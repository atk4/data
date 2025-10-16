<?php

declare(strict_types=1);

namespace Atk4\Data\Reference;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

class ContainsOne extends ContainsBase
{
    #[\Override]
    public function isOneToOne(): bool
    {
        return true;
    }

    #[\Override]
    public function ref(Model $ourModelOrEntity, array $defaults = []): Model
    {
        $this->assertOurModelOrEntity($ourModelOrEntity);

        $theirModel = $this->createTheirModel(array_merge($defaults, [
            'containedInEntity' => $ourModelOrEntity->isEntity() ? $ourModelOrEntity : null,
        ]));

        $this->setTheirModelPersistenceSeedData(
            $theirModel,
            $ourModelOrEntity->isEntity() && $this->getOurFieldValue($ourModelOrEntity) !== null
                ? [1 => $this->getOurFieldValue($ourModelOrEntity)]
                : []
        );

        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $this->onHookToTheirModel($theirModel, $spot, function (Model $theirEntity) {
                $ourEntity = $theirEntity->getModel()->containedInEntity;
                $this->assertOurModelOrEntity($ourEntity);
                $ourEntity->assertIsEntity();

                $ourEntity->assertNotDirty($this->getOurFieldName());

                $persistence = Persistence\Array_::assertInstanceOf($theirEntity->getModel()->getPersistence());
                $rows = $persistence->getRawDataByTable($theirEntity->getModel(), $this->tableAlias); // @phpstan-ignore method.deprecated
                assert(count($rows) <= 1);
                $ourEntity->set($this->getOurFieldName(), $rows !== [] ? array_first($rows) : null);
                if ($ourEntity->isDirty($this->getOurFieldName())) {
                    $ourEntity->save();
                }
            }, [], self::HOOK_PRIORITY_EARLY);
        }

        if ($ourModelOrEntity->isEntity()) {
            $theirModelOrig = $theirModel;
            $theirModel = $theirModel->tryLoadOne();

            if ($theirModel === null) {
                $theirModel = $theirModelOrig->createEntity();
            }
        }

        return $theirModel;
    }
}
