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

    public function getJoin(Model $entity = null): Join
    {
        if ($entity !== null) {
            $entity->assertIsEntity($this->getOwner());

            return $entity->getModel()->getElement($this->joinName);
        }

        return $this->getOwner()->getElement($this->joinName);
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
