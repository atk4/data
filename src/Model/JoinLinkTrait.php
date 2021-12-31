<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

trait JoinLinkTrait
{
    /** @var string|null The short name of the join link. */
    protected $joinName;

    public function hasJoin(): bool
    {
        return $this->joinName !== null;
    }

    public function getJoin(): Join
    {
        return $this->getOwner()->getElement($this->joinName);
    }

    public function setJoin(Join $join): self
    {
        $this->joinName = $join->short_name;

        return $this;
    }
}
