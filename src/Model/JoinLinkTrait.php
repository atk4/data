<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

use Atk4\Data\Model;

trait JoinLinkTrait
{
    /**
     * The short name of the join link.
     *
     * @var string|null
     */
    protected $joinName;

    private function assertIsOwnerEntity(Model $entity): void
    {
        $entity->assertIsEntity(/* TODO $this->getOwner() valid once not rebound to insatnce in Model */);
    }

    public function getJoin(Model $entity = null): Join
    {
        $model = $this->getOwner();
        if ($entity !== null) { // TODO non-null default?
            $this->assertIsOwnerEntity($entity);
            $model = $entity;
        }

        return $model->getElement($this->joinName);
    }

    public function hasJoin(): bool
    {
        return $this->joinName !== null;
    }

    public function setJoin(Join $join): self
    {
        $this->joinName = $join->short_name;

        return $this;
    }
}
