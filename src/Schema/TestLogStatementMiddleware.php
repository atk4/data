<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

if (!Connection::isDbal3x()) {
    trait TestLogStatementMiddlewareTrait
    {
        #[\Override]
        public function bindValue($param, $value, ParameterType $type): void
        {
            $this->_bindValue($param, $value, $type);
        }

        #[\Override]
        public function execute(): Result
        {
            return $this->_execute();
        }
    }
} else {
    trait TestLogStatementMiddlewareTrait
    {
        #[\Override]
        public function bindValue($param, $value, $type = ParameterType::STRING): bool
        {
            return $this->_bindValue($param, $value, $type);
        }

        #[\Override]
        public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
        {
            return $this->_bindParam($param, $variable, $type, $length);
        }

        #[\Override]
        public function execute($params = null): Result
        {
            assert($params === null);

            return $this->_execute();
        }
    }
}

class TestLogStatementMiddleware extends AbstractStatementMiddleware
{
    use TestLogStatementMiddlewareTrait;

    /** @var \WeakReference<TestLogConnectionMiddleware> */
    private \WeakReference $weakLogConnectionMiddleware;

    private string $sql;

    /** @var array<int|string, array{ParameterType::*, mixed}> */
    private array $params = [];

    public function __construct(Statement $wrappedStatement, TestLogConnectionMiddleware $logConnectionMiddleware, string $sql)
    {
        parent::__construct($wrappedStatement);

        $this->weakLogConnectionMiddleware = \WeakReference::create($logConnectionMiddleware);
        $this->sql = $sql;
    }

    /**
     * @param int|string                     $param
     * @param mixed                          $value
     * @param ParameterType|ParameterType::* $type
     */
    protected function _bindValue($param, $value, $type = ParameterType::STRING): ?bool
    {
        $this->setLogParam($param, $type, $value);

        return parent::bindValue($param, $value, $type); // @phpstan-ignore staticMethod.void (https://github.com/phpstan/phpstan/issues/13899)
    }

    /**
     * @param int|string       $param
     * @param mixed            $variable
     * @param ParameterType::* $type
     * @param int|null         $length
     *
     * @deprecated remove once DBAL 3.x support is dropped
     */
    protected function _bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
    {
        $this->setLogParam($param, $type, $variable);

        return parent::bindParam($param, $variable, $type, $length); // @phpstan-ignore staticMethod.notFound
    }

    /**
     * @param int|string       $param
     * @param ParameterType::* $type
     * @param mixed            $value
     */
    private function setLogParam($param, $type, $value): void
    {
        $this->params[$param] = [$type, $value];
    }

    protected function _execute(): Result
    {
        $this->weakLogConnectionMiddleware->get()->logStartQuery($this->sql, $this->params);

        return parent::execute();
    }
}
