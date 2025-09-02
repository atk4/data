<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

use Atk4\Data\Exception;

class MysqliAsyncConnection extends MysqlConnection
{
    protected ?\mysqli $mysqli = null;

    private ?float $queryStartedTs = null;

    public function __construct(string $sshHost, string $sshUser, string $dbHost, int $dbPort, string $dbUser, string $dbPassword, string $dbDatabase)
    {
        if ($sshHost !== 'mysqli' || $sshUser !== 'mysqli') {
            parent::__construct($sshHost, $sshUser, $dbHost, $dbPort, $dbUser, $dbPassword, $dbDatabase);

            return;
        }

        \Closure::bind(function () {
            $this->id = self::$nextId++;
        }, $this, parent::class)();

        mysqli_report(\MYSQLI_REPORT_OFF);

        $this->mysqli = new \mysqli($dbHost, $dbUser, $dbPassword, $dbDatabase, $dbPort);
    }

    #[\Override]
    public function __destruct()
    {
        if ($this->mysqli === null) {
            parent::__destruct();
        }
    }

    /**
     * @return never
     */
    private function assertNotCalled(): void
    {
        throw new Exception('Code invoked unexpectedly');
    }

    #[\Override]
    public function readStdout(\Closure $completeFx, float $maxWaitSeconds): string
    {
        if ($this->mysqli === null) {
            return parent::readStdout($completeFx, $maxWaitSeconds);
        }

        $this->assertNotCalled();
    }

    #[\Override]
    protected function execCmd(string $cmd, \Closure $completeFx): string
    {
        if ($this->mysqli === null) {
            return parent::execCmd($cmd, $completeFx);
        }

        $this->assertNotCalled();
    }

    #[\Override]
    public function sendQuery(string $sql): void
    {
        if ($this->mysqli === null) {
            parent::sendQuery($sql);

            return;
        }

        if ($this->enableDebugPrint) {
            echo "\n\n";
            $this->printDebugMessage(($this instanceof MysqlConnectionWithState ? '(' . ($this->inTransaction ? 'T' : '-') . ') ' : '') . 'query: ' . $sql);
        }

        $this->queryStartedTs = microtime(true);
        $this->mysqli->query($sql, \MYSQLI_ASYNC | \MYSQLI_STORE_RESULT);
    }

    #[\Override]
    public function hasMoreData(): bool
    {
        if ($this->mysqli === null) {
            return parent::hasMoreData();
        }

        if ($this->queryStartedTs === null) {
            return false;
        }

        $read = [$this->mysqli];
        $error = $read;
        $reject = $read;
        $poolRes = mysqli_poll($read, $error, $reject, 0, 100);
        assert($poolRes !== false);
        assert($error === []); // @phpstan-ignore identical.alwaysFalse, function.impossibleType
        assert($reject === []); // @phpstan-ignore identical.alwaysFalse, function.impossibleType

        return $read !== []; // @phpstan-ignore notIdentical.alwaysTrue
    }

    #[\Override]
    public function readResult(): MysqlResult
    {
        if ($this->mysqli === null) {
            return parent::readResult();
        }

        if ($this->enableDebugPrint) {
            echo '  ';
            $this->printDebugMessage('reading result');
        }

        assert($this->queryStartedTs !== null);

        while (!$this->hasMoreData());

        try {
            $mysqliRes = @$this->mysqli->reap_async_query();
            $rows = is_bool($mysqliRes)
                ? []
                : $mysqliRes->fetch_all(\MYSQLI_ASSOC);

            $elapsed = microtime(true) - $this->queryStartedTs;
        } finally {
            $this->queryStartedTs = null;
        }

        $queryRes = new MysqlResult();

        if ($mysqliRes === false) {
            $queryRes->error = 'ERROR ' . $this->mysqli->errno . ' (' . $this->mysqli->sqlstate . '): ' . $this->mysqli->error;
            if ($this->enableDebugPrint) {
                echo '    query error: ' . $queryRes->error . "\n";
            }

            return $queryRes;
        }

        $queryRes->error = null;
        if ($rows === []) {
            $queryRes->affectedRows = $this->mysqli->affected_rows;
        } else {
            $queryRes->rows = $rows;
        }
        $queryRes->elapsed = $elapsed;

        return $queryRes;
    }
}
