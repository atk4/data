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
        // we need to collect ID
        $this->setId($entity, $entity->getDataRef()[$this->master_field]);
        if ($this->getId($entity) === null) {
            return;
        }

        try {
            $data = Persistence\Array_::assertInstanceOf($this->getOwner()->getPersistence())
                ->load($this->createFakeForeignModel(), $this->getId($entity));
        } catch (Exception $e) {
            throw (new Exception('Unable to load joined record', $e->getCode(), $e))
                ->addMoreInfo('table', $this->foreign_table)
                ->addMoreInfo('id', $this->getId($entity));
        }
        $dataRef = &$entity->getDataRef();
        $dataRef = array_merge($data, $entity->getDataRef());
    }
}
