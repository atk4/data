<?php

declare(strict_types=1);

namespace Atk4\Data\Model;

trait JoinLinkTrait
{
    protected ?string $joinName = null;

    public function hasJoin(): bool
    {
        return $this->joinName !== null;
    }

    public function getJoin(): Join
    {
        return $this->getOwner()->getJoin($this->joinName);
    }
}
