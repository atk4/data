<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

use Atk4\Data\Exception;

abstract class MysqlConnection
{
    private static int $nextId = 1;

    protected int $id;

    /** @var resource */
    protected $ssh;
    /** @var resource */
    protected $stdin;
    /** @var resource */
    protected $stdout;
    /** @var resource */
    protected $stderr;

    public bool $enableDebugPrint = false;

    public int $threadId;

    public function __construct(string $sshHost, string $sshUser, string $dbHost, int $dbPort, string $dbUser, string $dbPassword, string $dbDatabase)
    {
        $this->id = self::$nextId++;

        if ($sshHost === 'exec' && $sshUser === 'exec') {
            $pipesSpec = [
                ['pipe', 'r'],
                ['pipe', 'w'],
                ['pipe', 'w'],
            ];

            // https://stackoverflow.com/questions/1401002/how-to-trick-an-application-into-thinking-its-stdout-is-a-terminal-not-a-pipe/20401674#20401674
            $this->ssh = proc_open('script -qfc sh /dev/null 2>&1', $pipesSpec, $pipes);
            assert(is_resource($this->ssh));

            $this->stdin = $pipes[0];
            $this->stdout = $pipes[1];
            $this->stderr = $pipes[2];

            stream_set_blocking($this->stdin, true);
            stream_set_blocking($this->stdout, true);
            stream_set_blocking($this->stderr, true);

            $this->readStdout(static fn ($v) => str_contains($v, '$ ') || str_contains($v, '# '), 30.0);
        } else {
            $createCallbackFx = static function (string $type) {
                return static function (...$args) use ($type) {
                    print_r(array_merge(['callback_type' => $type], $args));
                };
            };

            $this->ssh = ssh2_connect($sshHost, 22, [], [
                'ignore' => $createCallbackFx('ignore'),
                'debug' => $createCallbackFx('debug'),
                'macerror' => $createCallbackFx('macerror'),
                'disconnect' => $createCallbackFx('disconnect'),
            ]);
            ssh2_auth_agent($this->ssh, $sshUser);

            $this->stdin = ssh2_shell($this->ssh);
            $this->stdout = $this->stdin;
            $this->stderr = ssh2_fetch_stream($this->stdin, SSH2_STREAM_STDERR);

            stream_set_blocking($this->stdin, true);
            stream_set_blocking($this->stdout, true);
            stream_set_blocking($this->stderr, true);

            $this->readStdout(static fn ($v) => str_contains($v, 'Last login: '), 30.0);
        }

        $mysqlCmd = 'mysql -h' . escapeshellarg($dbHost)
            . ' -P' . escapeshellarg((string) $dbPort)
            . ' -u' . escapeshellarg($dbUser)
            . ' -p' . escapeshellarg($dbPassword)
            . ' -D' . escapeshellarg($dbDatabase);
        $this->execCmd($mysqlCmd, static function ($v) {
            if (str_contains($v, 'exceeded the \'max_user_connections\' resource')) {
                throw new Exception('Too many connections');
            }

            return str_contains($v, 'Server version: ') && str_contains($v, "\n" . 'mysql> ');
        });

        self::sendQuery('select CONNECTION_ID()');
        $res = self::readResult();
        assert($res->error === null);
        $this->threadId = (int) $res->rows[0]['CONNECTION_ID()'];
    }

    public function __destruct()
    {
        fwrite($this->stdin, 'exit' . "\n");
    }

    /**
     * @param resource $stream
     */
    protected function readNonBlocking($stream, ?int $maxSingleReadLength = null): string
    {
        if (\PHP_MAJOR_VERSION === 7 && $maxSingleReadLength === null) {
            $maxSingleReadLength = -1;
        }

        try {
            stream_set_blocking($stream, false);

            return stream_get_contents($stream, $maxSingleReadLength);
        } finally {
            stream_set_blocking($stream, true);
        }
    }

    /**
     * @param resource               $stream
     * @param \Closure(string): bool $completeFx
     */
    protected function readSemiBlocking($stream, \Closure $completeFx, float $maxWaitSeconds, ?int $maxSingleReadLength = null): string
    {
        $startTs = microtime(true);
        $res = '';
        while (true) {
            $res .= str_replace("\r\n", "\n", $this->readNonBlocking($stream, $maxSingleReadLength));

            if ($completeFx($res)) {
                return $res;
            }

            if (microtime(true) >= $startTs + $maxWaitSeconds) {
                throw (new Exception('Stream read timeout elapsed'))
                    ->addMoreInfo('data', $res)
                    ->addMoreInfo('stderr', $this->readNonBlocking($this->stderr));
            }

            usleep(2_000);
        }
    }

    /**
     * @param \Closure(string): bool $completeFx
     */
    public function readStdout(\Closure $completeFx, float $maxWaitSeconds): string
    {
        $stdout = $this->readSemiBlocking($this->stdout, $completeFx, $maxWaitSeconds);

        $stderr = $this->readNonBlocking($this->stderr);
        if ($stderr !== '') {
            throw (new Exception('Non-empty stderr'))
                ->addMoreInfo('stdout', $stdout)
                ->addMoreInfo('stderr', $stderr);
        }

        return $stdout;
    }

    protected function printDebugMessage(string $message): void
    {
        echo '#' . $this->id . ' ' . (new \DateTime())->format('H:i:s.u') . ' ' . $message . "\n";
    }

    /**
     * @param \Closure(string): bool $completeFx
     */
    protected function execCmd(string $cmd, \Closure $completeFx): string
    {
        if ($this->enableDebugPrint) {
            echo "\n\n";
            $this->printDebugMessage('executing cmd: ' . $cmd);
        }
        fwrite($this->stdin, $cmd . "\n");
        $res = $this->readStdout($completeFx, 30.0);

        return $res;
    }

    public function sendQuery(string $sql): void
    {
        $sql .= ';';

        if ($this->enableDebugPrint) {
            echo "\n\n";
            $this->printDebugMessage(($this instanceof MysqlConnectionWithState ? '(' . ($this->inTransaction ? 'T' : '-') . ') ' : '') . 'query: ' . $sql);
        }
        fwrite($this->stdin, $sql . "\n");

        $this->readSemiBlocking($this->stdout, static fn ($v) => str_contains($v, ";\r\n"), 30.0, 1); // "\r\n" even on linux
    }

    public function hasMoreData(): bool
    {
        $read = [$this->stdout, $this->stderr];
        $write = null;
        $except = null;

        return stream_select($read, $write, $except, 0) > 0;
    }

    protected function readResultStr(): string
    {
        if ($this->enableDebugPrint) {
            echo '  ';
            $this->printDebugMessage('reading result');
        }
        $res = $this->readStdout(static fn ($v) => str_contains($v, "\n" . 'mysql> ') || str_contains($v, "\n\x07" . 'mysql> '), 30.0);

        $endStr = "\n" . (strpos($res, "\x07") !== false ? "\x07" : '') . 'mysql> ';
        $endPos = strpos($res, $endStr);
        if ($endPos !== strlen($res) - strlen($endStr)) {
            throw (new Exception('Unexpected raw response'))
                ->addMoreInfo('data', $res);
        }

        return substr($res, 0, $endPos);
    }

    public function readResult(): MysqlResult
    {
        $str = $this->readResultStr();
        $lines = explode("\n", $str);
        $lastLine = array_pop($lines);

        $res = new MysqlResult();

        if ($lastLine === '') {
            $lastLine = array_pop($lines);
        } elseif (count($lines) === 0) {
            $res->error = $lastLine;
            if ($this->enableDebugPrint) {
                echo '    query error: ' . $res->error . "\n";
            }

            return $res;
        }

        if (preg_match('~^Records: \d+  Duplicates: \d+  Warnings: 0$~', $lastLine, $matches)) {
            $lastLine = array_pop($lines);
        } elseif (preg_match('~^Rows matched: \d+  Changed: \d+  Warnings: 0$~', $lastLine, $matches)) {
            $lastLine = array_pop($lines);
        }

        if (!preg_match('~^(?:Query OK, (\d+) rows? affected)?(Empty set)?(?:(\d+) rows? in set)? \((\d+.\d+) sec\)$~', $lastLine, $matches)) {
            throw (new Exception('Failed to parse response'))
                ->addMoreInfo('data', $str);
        }

        $res->error = null;
        $res->elapsed = (float) $matches[4];

        if ($matches[1] !== '') {
            $res->affectedRows = (int) $matches[1];
        } elseif ($matches[2] !== '') {
            // "empty set", no header
        } else {
            assert($matches[3] !== '');
            $rCount = (int) $matches[3];

            $header = false;
            $res->rows = [];
            foreach ($lines as $line) {
                if (strpos($line, '+') === 0) {
                    continue;
                }

                $cols = array_slice(preg_split('~(?:^| +)\|(?: +|$)~', $line), 1, -1);
                if ($header === false) {
                    $header = $cols;

                    continue;
                }

                $res->rows[] = array_combine($header, $cols);
            }

            assert(count($res->rows) === $rCount);
        }

        return $res;
    }
}
