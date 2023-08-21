<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class Join extends Model\Join
{
    public function afterLoad(Model $entity): void
    {
        $model = $this->getOwner();

        // we need to collect ID
        $this->setId($entity, $entity->getDataRef()[$this->masterField]);
        if ($this->getId($entity) === null) {
            return;
        }

        try {
            $foreignData = Persistence\Array_::assertInstanceOf($model->getPersistence())
                ->load($this->createFakeForeignModel(), $this->getId($entity));
        } catch (Exception $e) {
            throw (new Exception('Unable to load joined record', $e->getCode(), $e))
                ->addMoreInfo('table', $this->foreignTable)
                ->addMoreInfo('id', $this->getId($entity));
        }

        $dataRef = &$entity->getDataRef();
        foreach ($model->getFields() as $field) {
            if ($field->hasJoin() && $field->getJoin()->shortName === $this->shortName) {
                $dataRef[$field->shortName] = $foreignData[$field->getPersistenceName()];
            }
        }
    }
}
