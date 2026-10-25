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
        try {
            return new TestLogStatementMiddleware(parent::prepare($sql), $this, $sql);
        } catch (DbalDriverException $e) {
            $this->logStartQuery('-- ### PREPARE ERROR ###' . "\n" . $sql);

            throw $e;
        }
    }

    protected function _beginTransaction(): ?bool
    {
        $this->logStartQuery('"START TRANSACTION"');

        return parent::beginTransaction(); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
    }

    protected function _commit(): ?bool
    {
        $this->logStartQuery('"COMMIT"');

        return parent::commit(); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
    }

    protected function _rollBack(): ?bool
    {
        $this->logStartQuery('"ROLLBACK"');

        return parent::rollBack(); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
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
