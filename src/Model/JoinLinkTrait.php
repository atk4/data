<?php

declare(strict_types=1);

namespace atk4\data\Model;

trait JoinLinkTrait
{
    /**
     * The short name of the join link.
     *
     * @var string
     */
    protected $join;

    public function getJoin(): Join
    {
        return $this->getOwner()->getElement($this->join);
    }

    public function hasJoin(): bool
    {
        return $this->join && $this->getOwner()->hasElement($this->join);
    }

    public function setJoin(Join $join): self
    {
        $this->join = $join->short_name;

        return $this;
    }
}
