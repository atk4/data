<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

class ExecuteException extends Exception
{
    public function getDebugQuery(): string
    {
        return $this->getParams()['query'];
    }
}
