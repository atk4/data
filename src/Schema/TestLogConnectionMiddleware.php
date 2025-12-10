<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

class TestLogConnectionMiddleware extends AbstractConnectionMiddleware
{
    #[\Override]
    public function exec(string $sql): int
    {
        $this->logStartQuery($sql);

        return parent::exec($sql);
    }

    #[\Override]
    public function query(string $sql): Result
    {
        $this->logStartQuery($sql);

        return parent::query($sql);
    }

    #[\Override]
    public function prepare(string $sql): Statement
    {
        return new TestLogStatementMiddleware(parent::prepare($sql), $this, $sql);
    }

    #[\Override]
    public function beginTransaction(): bool
    {
        $this->logStartQuery('"START TRANSACTION"');

        return parent::beginTransaction();
    }

    #[\Override]
    public function commit(): bool
    {
        $this->logStartQuery('"COMMIT"');

        return parent::commit();
    }

    #[\Override]
    public function rollBack(): bool
    {
        $this->logStartQuery('"ROLLBACK"');

        return parent::rollBack();
    }

    /**
     * @param array<int|string, array{ParameterType::*, mixed}> $params
     */
    public function logStartQuery(string $sql, ?array $params = null): void
    {
        // remove once DBAL 3.7 support is dropped
        // https://github.com/doctrine/dbal/pull/6197
        $sql = preg_replace('~^(?:SAVEPOINT|RELEASE SAVEPOINT|ROLLBACK TO SAVEPOINT) DOCTRINE\K2_SAVEPOINT(?=_\d+$)~', '', $sql);

        $test = TestCase::getTestFromBacktrace();
        \Closure::bind(static fn () => $test->logQuery($sql, $params ?? []), null, TestCase::class)();
    }
}
