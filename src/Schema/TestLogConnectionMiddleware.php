<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

if (!Connection::isDbal3x()) {
    trait TestLogConnectionMiddlewareTrait
    {
        #[\Override]
        public function beginTransaction(): void
        {
            $this->_beginTransaction();
        }

        #[\Override]
        public function commit(): void
        {
            $this->_commit();
        }

        #[\Override]
        public function rollBack(): void
        {
            $this->_rollBack();
        }
    }
} else {
    trait TestLogConnectionMiddlewareTrait
    {
        #[\Override]
        public function beginTransaction(): bool
        {
            return $this->_beginTransaction();
        }

        #[\Override]
        public function commit(): bool
        {
            return $this->_commit();
        }

        #[\Override]
        public function rollBack(): bool
        {
            return $this->_rollBack();
        }
    }
}

class TestLogConnectionMiddleware extends AbstractConnectionMiddleware
{
    use TestLogConnectionMiddlewareTrait;

    #[\Override]
    public function exec(string $sql): int
    {
        $this->logStartQuery($sql);

        $start = hrtime(true);

        try {
            return parent::exec($sql);
        } finally {
            OracleConnectionStats::recordExecute(hrtime(true) - $start);
        }
    }

    #[\Override]
    public function query(string $sql): Result
    {
        $this->logStartQuery($sql);

        $start = hrtime(true);

        try {
            return parent::query($sql);
        } finally {
            OracleConnectionStats::recordExecute(hrtime(true) - $start);
        }
    }

    #[\Override]
    public function prepare(string $sql): Statement
    {
        $this->logStartQuery($sql);

        $start = hrtime(true);

        try {
            $statement = parent::prepare($sql);
        } finally {
            OracleConnectionStats::recordPrepare(hrtime(true) - $start);
        }

        return new TestLogStatementMiddleware(
            $statement,
            $this,
            $sql
        );
        /*
       try {
            return new TestLogStatementMiddleware(parent::prepare($sql), $this, $sql);
        } catch (DbalDriverException $e) {
            $this->logStartQuery('-- ### PREPARE ERROR ###' . "\n" . $sql);

            throw $e;
        }
        */
    }

    protected function _beginTransaction(): ?bool
    {
        $this->logStartQuery('"START TRANSACTION"');

        $start = hrtime(true);

        try {
            return parent::beginTransaction(); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
        } finally {
            OracleConnectionStats::recordExecute(hrtime(true) - $start);
        }
    }

    protected function _commit(): ?bool
    {
        $this->logStartQuery('"COMMIT"');

        $start = hrtime(true);

        try {
            return parent::commit(); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
        } finally {
            OracleConnectionStats::recordExecute(hrtime(true) - $start);
        }
    }

    protected function _rollBack(): ?bool
    {
        $this->logStartQuery('"ROLLBACK"');

        $start = hrtime(true);

        try {
            return parent::rollBack(); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
        } finally {
            OracleConnectionStats::recordExecute(hrtime(true) - $start);
        }
    }

    /**
     * @param array<int|string, array{ParameterType::*, mixed}> $params
     */
    public function logStartQuery(string $sql, ?array $params = null): void
    {
        $test = TestCase::getTestFromBacktrace();
        \Closure::bind(static fn () => $test->logQuery($sql, $params ?? []), null, TestCase::class)();
    }
}
