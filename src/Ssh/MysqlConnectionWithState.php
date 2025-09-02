<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

use Atk4\Data\Exception;

class MysqlConnectionWithState extends MysqlConnectionThrow
{
    public const ISOLATION_LEVEL_SERIALIZABLE = 'SERIALIZABLE';
    public const ISOLATION_LEVEL_REPEATABLE_READ = 'REPEATABLE READ';
    public const ISOLATION_LEVEL_READ_COMMITTED = 'READ COMMITTED';
    public const ISOLATION_LEVEL_READ_UNCOMMITTED = 'READ UNCOMMITTED';

    /** @var array<string, array{bool, string}> */
    private static array $serverVersionCache;

    public ?string $lastQuery = null;

    public ?float $inQuerySinceTs = null;

    public bool $serverIsMariaDB;

    public string $serverVersion;

    /** @var self::ISOLATION_LEVEL_* */
    public ?string $isolationLevel = null;

    public bool $inTransaction = false;

    private int $startTransactionCounter = 0;

    public bool $enableAssertInTransactionUsingQuery = false;

    public function __construct(string $sshHost, string $sshUser, string $dbHost, int $dbPort, string $dbUser, string $dbPassword, string $dbDatabase)
    {
        parent::__construct($sshHost, $sshUser, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);

        $serverVersionCacheKey = $dbHost . ':' . $dbPort;
        if (!isset(self::$serverVersionCache[$serverVersionCacheKey])) {
            parent::sendQuery('select version()');
            $res = parent::readResult();
            assert(preg_match('~(\d+\.\d+\.\d+)(.*MariaDB)?~i', reset($res->rows[0]), $matches));
            self::$serverVersionCache[$serverVersionCacheKey] = [
                ($matches[2] ?? '') !== '',
                $matches[1],
            ];
        }

        [$this->serverIsMariaDB, $this->serverVersion] = self::$serverVersionCache[$serverVersionCacheKey];
    }

    private function assertInTransactionIsCorrect(): void
    {
        if (!$this->enableAssertInTransactionUsingQuery) {
            return;
        }

        $inTransactionActual = $this->queryInTransaction();
        if ($this->inTransaction !== $inTransactionActual) {
            throw (new Exception('Wrong "inTransaction" assumed'))
                ->addMoreInfo('tracked', $this->inTransaction)
                ->addMoreInfo('actual', $inTransactionActual);
        }
    }

    #[\Override]
    public function sendQuery(string $sql): void
    {
        if (preg_match('~start +transaction~i', $sql) && $this->inTransaction) {
            throw new Exception('Cannot start transaction when in transaction already');
        }

        assert($this->inQuerySinceTs === null);

        parent::sendQuery($sql);

        $this->lastQuery = $sql;
        $this->inQuerySinceTs = microtime(true);
    }

    #[\Override]
    public function readResult(): MysqlResultWithoutError
    {
        $exception = null;
        try {
            $res = parent::readResult();
        } catch (MysqlException $exception) {
            $exception->addMoreInfo('sql', $this->lastQuery);
        }

        $this->inQuerySinceTs = null;

        if ($exception !== null && $exception->getCode() === 1317) {
            throw $exception;
        }

        $sqlLower = trim(preg_replace('~\s+~', ' ', strtolower($this->lastQuery)));

        if (str_contains($sqlLower, 'start transaction') && $exception === null) {
            $this->inTransaction = true;
            ++$this->startTransactionCounter;
        } elseif (str_contains($sqlLower, 'commit') || str_contains($sqlLower, 'rollback')) {
            $this->inTransaction = false;
        }

        if ($exception !== null) {
            // ERROR 1020: Record has changed since last read in table 'xxx' - emit by MariaDB 11.6.2 and higher
            // ERROR 1213: Deadlock found when trying to get lock; try restarting transaction - emit by MySQL 8.0.17 and lower, MariaDB 10.5.21 and lower, 10.6.14 and lower, any 10.7, any 10.8 only
            if (in_array($exception->getCode(), [1020, 1213], true)) {
                $this->inTransaction = false;
            }
        }

        $this->assertInTransactionIsCorrect();

        if ($exception !== null) {
            throw $exception;
        }

        if (str_contains($sqlLower, ' transaction isolation level')) {
            assert(str_contains($sqlLower, 'set session transaction isolation level'));

            if (str_contains($sqlLower, 'serializable')) {
                $this->isolationLevel = self::ISOLATION_LEVEL_SERIALIZABLE;
            } elseif (str_contains($sqlLower, 'repeatable read')) {
                $this->isolationLevel = self::ISOLATION_LEVEL_REPEATABLE_READ;
            } elseif (str_contains($sqlLower, 'read committed')) {
                $this->isolationLevel = self::ISOLATION_LEVEL_READ_COMMITTED;
            } elseif (str_contains($sqlLower, 'read uncommitted')) {
                $this->isolationLevel = self::ISOLATION_LEVEL_READ_UNCOMMITTED;
            } else {
                throw (new Exception('Unexpected transaction isolation level'))
                    ->addMoreInfo('sql', $this->lastQuery);
            }
        }

        return $res;
    }

    public function waitUntilQueryIsExecuting(MysqlConnectionThrow $freeConnection): void
    {
        $startTs = microtime(true);
        do {
            $freeConnection->sendQuery('select command, state from information_schema.processlist where id = ' . $this->threadId);
            $res = $freeConnection->readResult();

            // TODO report to MariaDB that when "select sleep(10)" is interrupted early, no error is emit
            // and processlist result is not enough to detect "fully started" query
            if ($res->rows[0]['command'] === 'Query') {
                return;
            }

            usleep(1_000);
        } while (microtime(true) - $startTs < 2);

        throw new Exception('Waiting too long for query to start executing');
    }

    /**
     * @template T
     *
     * @param \Closure(): T $fx
     *
     * @return T
     */
    public function executeAndRetryOnInterruptedQuery(\Closure $fx)
    {
        for ($i = 0; $i < 50; ++$i) {
            try {
                return $fx();
            } catch (MysqlException $e) {
                if ($e->getCode() !== 1317) {
                    throw $e;
                }
            }
        }

        throw new Exception('Too many interrupted query retries', 0, $e); // @phpstan-ignore variable.undefined (https://github.com/phpstan/phpstan/issues/13125)
    }

    /**
     * @return array{string, string, ?bool, ?int<0, max>}
     */
    private function queryIsolationLevelRaw(): array
    {
        $hasInfoTable = $this->serverIsMariaDB ? true : version_compare($this->serverVersion, '5.7') < 0;

        $res = $this->executeAndRetryOnInterruptedQuery(function () use ($hasInfoTable) {
            parent::sendQuery($hasInfoTable
                ? <<<'EOD'
                    (select LOWER(VARIABLE_NAME) as Variable_name, VARIABLE_VALUE as Value from information_schema.SESSION_VARIABLES where VARIABLE_NAME IN('TX_ISOLATION', 'TRANSACTION_ISOLATION', 'IN_TRANSACTION'))
                    union all
                    (select * from information_schema.SESSION_STATUS where VARIABLE_NAME = 'COM_BEGIN')
                    EOD
                : 'show session variables where Variable_name = \'tx_isolation\' or Variable_name = \'transaction_isolation\'');

            return parent::readResult();
        });

        $resCol = array_column($res->rows, 'Value', 'Variable_name');

        $isolationLevel = $resCol['transaction_isolation'] ?? $resCol['tx_isolation'];
        assert($isolationLevel === ($resCol['tx_isolation'] ?? $resCol['transaction_isolation']));

        return [
            isset($resCol['transaction_isolation']) ? 'transaction_isolation' : 'tx_isolation',
            $isolationLevel,
            $this->serverIsMariaDB ? (bool) $resCol['in_transaction'] : null,
            $hasInfoTable ? (int) $resCol['COM_BEGIN'] : null,
        ];
    }

    private function queryInTransaction(): bool
    {
        assert($this->inQuerySinceTs === null);

        $res = $this->_queryInTransaction();

        if ($this->enableDebugPrint) {
            echo '    verifying inTransaction: ' . ($res ? 'T' : '-') . ($this->inTransaction === $res ? ' ✓' : ' ## MISMATCH ##') . "\n";
        }

        return $res;
    }

    private function _queryInTransaction(): bool
    {
        // based on https://dba.stackexchange.com/questions/128726/transaction-identifier-possible-with-mysql#comment239993_129055
        $enableDebugPrintOrig = $this->enableDebugPrint;
        $this->enableDebugPrint = false;
        try {
            [$isolationLevelVariableName, $isolationLevelOrig, $inTransactionMariadb, $comBegin] = $this->queryIsolationLevelRaw();

            if ($comBegin !== null) {
                assert($this->startTransactionCounter === $comBegin);
            }

            try {
                $this->executeAndRetryOnInterruptedQuery(function () {
                    parent::sendQuery('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
                    parent::readResult();
                });
            } catch (MysqlException $e) {
                // ERROR 1568 (25001): Transaction isolation level can't be changed while a transaction is in progress"
                if ($e->getCode() === 1568) {
                    if ($inTransactionMariadb !== null) {
                        assert($inTransactionMariadb);
                    }

                    return true;
                }

                throw $e;
            }

            $this->executeAndRetryOnInterruptedQuery(function () use ($isolationLevelVariableName, $isolationLevelOrig) {
                parent::sendQuery('set session ' . $isolationLevelVariableName . ' = \'' . $isolationLevelOrig . '\'');
                parent::readResult();
            });
        } finally {
            $this->enableDebugPrint = $enableDebugPrintOrig;
        }

        if ($inTransactionMariadb !== null) {
            assert(!$inTransactionMariadb);
        }

        return false;
    }
}
