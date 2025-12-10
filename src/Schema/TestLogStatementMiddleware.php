<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;

class TestLogStatementMiddleware extends AbstractStatementMiddleware
{
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

    #[\Override]
    public function bindValue($param, $value, $type = ParameterType::STRING)
    {
        $this->setLogParam($param, $type, $value); // @phpstan-ignore argument.type

        return parent::bindValue($param, $value, $type);
    }

    #[\Override]
    public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null)
    {
        $this->setLogParam($param, $type, $variable); // @phpstan-ignore argument.type

        return parent::bindParam($param, $variable, $type, $length);
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

    #[\Override]
    public function execute($params = null): Result
    {
        assert($params === null);

        $this->weakLogConnectionMiddleware->get()->logStartQuery($this->sql, $this->params);

        return parent::execute();
    }
}
