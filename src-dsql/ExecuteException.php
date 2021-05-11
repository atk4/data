<?php

declare(strict_types=1);

namespace Atk4\Dsql;

class ExecuteException extends Exception
{
    public function getErrorMessage(): string
    {
        return $this->getParams()['error'];
    }

    public function getDebugQuery(): string
    {
        return $this->getParams()['query'];
    }
}
