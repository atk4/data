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
     *
     * @param array $defaults Properties
     */
    public function ref($defaults = []): Model
    {
        $ourModel = $this->getOurModel();

        // get model
        // will not use ID field (no, sorry, will have to use it)
        $theirModel = $this->getTheirModel(array_merge($defaults, [
            'contained_in_root_model' => $ourModel->contained_in_root_model ?: $ourModel,
            //'id_field'              => false,
            'table' => $this->table_alias,
        ]));

        // set some hooks for ref_model
        foreach ([Model::HOOK_AFTER_SAVE, Model::HOOK_AFTER_DELETE] as $spot) {
            $theirModel->onHook($spot, function ($theirModel) {
                $rows = iterator_to_array($theirModel->persistence->getRawDataIterator($this->table_alias));
                $this->getOurModel()->save([
                    $this->getOurFieldName() => $rows ?: null,
                ]);
            });
        }

        return $theirModel;
    }
}
