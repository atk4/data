<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Doctrine\DBAL\Statement;

trait ExpressionTrait
{
    protected function _executeStatement(Statement $statement, bool $fromExecuteStatement)
    {
        $sql = \Closure::bind(static fn () => $statement->sql, null, Statement::class)();
        if (preg_match('~^\s*+select(?=\s|$)~i', $sql)) {
            return parent::_executeStatement($statement, $fromExecuteStatement);
        }

        return $this->connection->atomic(function () use ($statement, $fromExecuteStatement) {
            return parent::_executeStatement($statement, $fromExecuteStatement);
        });
    }
}
