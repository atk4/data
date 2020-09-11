<?php

declare(strict_types=1);

namespace atk4\data\Reference;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * ContainsMany reference.
 */
class ContainsMany extends ContainsOne
{
    protected function getDefaultPersistence(Model $theirModel)
    {
        return new Persistence\ArrayOfStrings([
            $this->table_alias => $this->getOurFieldValue() ?: [],
        ]);
    }

    /**
     * Returns referenced model.
     */
    public function ref(array $defaults = []): Model
    {
        $ourModel = $this->getOurModel();

        // get model
        $theirModel = $this->getTheirModel(array_merge($defaults, [
            'contained_in_root_model' => $ourModel->contained_in_root_model ?: $ourModel,
            'table' => $this->table_alias,
        ]));

        // set some hooks for ref_model
        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $theirModel->onHook($spot, function ($theirModel) {
                $this->getOurModel()->save([
                    $this->getOurFieldName() => $theirModel->toQuery()->export() ?: null,
                ]);
            });
        }

        return $theirModel;
    }
}
