<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

class MysqlConnectionThrow extends MysqliAsyncConnection
{
    #[\Override]
    public function readResult(): MysqlResultWithoutError
    {
        $res = parent::readResult();

        if ($res->error !== null) {
            $exception = new MysqlException('Query error: ' . $res->error, $res->getErrorCode());
            $exception->elapsed = $res->elapsed;

            throw $exception;
        }

        return new MysqlResultWithoutError($res);
    }
}
