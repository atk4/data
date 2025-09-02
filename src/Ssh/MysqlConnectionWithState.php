<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

use Atk4\Data\Exception;

class MysqlConnectionWithState extends MysqliAsyncConnection
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

    public bool $enableAssertInTransactionUsingQuery = false;

    public function __construct(string $sshHost, string $sshUser, string $dbHost, int $dbPort, string $dbUser, string $dbPassword, string $dbDatabase)
    {
        parent::__construct($sshHost, $sshUser, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);

        $serverVersionCacheKey = $dbHost . ':' . $dbPort;
        if (!isset(self::$serverVersionCache[$serverVersionCacheKey])) {
            parent::sendQuery('select version()');
            $res = parent::readResult();
            assert($res->error === null);
            assert(preg_match('~(\d+\.\d+\.\d+)(.*MariaDB)?~i', reset($res->rows[0]), $matches));
            self::$serverVersionCache[$serverVersionCacheKey] = [
                ($matches[2] ?? '') !== '',
                $matches[1],
            ];
        }

        [$this->serverIsMariaDB, $this->serverVersion] = self::$serverVersionCache[$serverVersionCacheKey];
    }

    /** @var self::ISOLATION_LEVEL_* */
    public ?string $isolationLevel = null;

    public bool $inTransaction = false;

    private int $startTransactionCounter = 0;

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
    public function readResult(): MysqlResult
    {
        $res = parent::readResult();

        $this->inQuerySinceTs = null;

        $sqlLower = trim(preg_replace('~\s+~', ' ', strtolower($this->lastQuery)));

        if (str_contains($sqlLower, 'start transaction') && $res->error === null) {
            $this->inTransaction = true;
            ++$this->startTransactionCounter;
        } elseif (str_contains($sqlLower, 'commit') || str_contains($sqlLower, 'rollback')) {
            $this->inTransaction = false;
        }

        if ($res->error !== null) {
            // handle deadlock error:
            // ERROR 1205: Lock wait timeout exceeded; try restarting transaction - emit by any MySQL and MariaDB version
            // ERROR 1213: Deadlock found when trying to get lock; try restarting transaction - emit by MySQL 8.0.17 and lower, MariaDB 10.5.21 and lower, 10.6.14 and lower, any 10.7, any 10.8 only
            // ERROR 1020: Record has changed since last read in table 'xxx' - emit by MariaDB 11.6.2 and higher
            // and throw for any other error
            if (str_starts_with($res->error, 'ERROR 1205 (') || str_starts_with($res->error, 'ERROR 1213 (') || str_starts_with($res->error, 'ERROR 1020 (')) {
                $this->inTransaction = $this->queryInTransaction(); // TODO explain why sometime the transaction is ended for the same error code - does the behaviour depend on database version only?

                return $res;
            }

            throw (new Exception('Query error: ' . $res->error))
                ->addMoreInfo('sql', $this->lastQuery);
        }

        $this->assertInTransactionIsCorrect();

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

    /**
     * @return array{string, string, ?bool, ?int<0, max>}
     */
    private function queryIsolationLevelRaw(): array
    {
        $hasInfoTable = $this->serverIsMariaDB ? true : version_compare($this->serverVersion, '5.7') < 0;

        parent::sendQuery($hasInfoTable
            ? <<<'EOD'
                (select LOWER(VARIABLE_NAME) as Variable_name, VARIABLE_VALUE as Value from information_schema.SESSION_VARIABLES where VARIABLE_NAME IN('TX_ISOLATION', 'TRANSACTION_ISOLATION', 'IN_TRANSACTION'))
                union all
                (select * from information_schema.SESSION_STATUS where VARIABLE_NAME = 'COM_BEGIN')
                EOD
            : 'show session variables where Variable_name = \'tx_isolation\' or Variable_name = \'transaction_isolation\'');
        $res = parent::readResult();
        assert($res->error === null);

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

        // based on https://dba.stackexchange.com/questions/128726/transaction-identifier-possible-with-mysql#comment239993_129055
        $enableDebugPrintOrig = $this->enableDebugPrint;
        $this->enableDebugPrint = false;
        try {
            [$isolationLevelVariableName, $isolationLevelOrig, $inTransactionMariadb, $comBegin] = $this->queryIsolationLevelRaw();

            if ($comBegin !== null) {
                assert($this->startTransactionCounter === $comBegin);
            }

            parent::sendQuery('SET TRANSACTION ISOLATION LEVEL SERIALIZABLE');
            $res = parent::readResult();
            // ERROR 1568 (25001): Transaction isolation level can't be changed while a transaction is in progress"
            if ($res->error !== null && str_contains($res->error, 'ERROR 1568 (')) {
                if ($inTransactionMariadb !== null) {
                    assert($inTransactionMariadb);
                }

                return true;
            }
            assert($res->error === null);

            parent::sendQuery('set session ' . $isolationLevelVariableName . ' = \'' . $isolationLevelOrig . '\'');
            $res = parent::readResult();
            assert($res->error === null);
        } finally {
            $this->enableDebugPrint = $enableDebugPrintOrig;
        }

        if ($inTransactionMariadb !== null) {
            assert(!$inTransactionMariadb);
        }

        return false;
    }
}
