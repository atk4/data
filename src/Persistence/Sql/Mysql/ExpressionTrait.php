<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Connection as SqlConnection;

trait ExpressionTrait
{
    protected function hasNativeNamedParamSupport(): bool
    {
        // TODO use Connection::getNativeConnection() once only DBAL 3.3+ is supported
        // https://github.com/doctrine/dbal/pull/5037
        $dbalConnection = $this->connection->connection();
        $nativeConnection = \Closure::bind(function () use ($dbalConnection) {
            return SqlConnection::getDriverFromDbalDriverConnection($dbalConnection->getWrappedConnection());
        }, null, SqlConnection::class)();

        return !$nativeConnection instanceof \mysqli;
    }
}
