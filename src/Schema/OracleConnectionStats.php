<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

/**
 * Collects Oracle connection and SQL timing statistics during PHPUnit tests.
 *
 * Statistics are aggregated by TestCase source file and globally for the
 * whole PHP process.
 *
 * @internal
 */
final class OracleConnectionStats
{
    /**
     * @var array<string, array{
     *     connections: int,
     *     attempts: int,
     *     retries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * }>
     */
    private static array $connectionsByFile = [];

    private static int $connections = 0;
    private static int $connectionAttempts = 0;
    private static int $connectionRetries = 0;
    private static int $connectionTotalNs = 0;
    private static int $connectionMinNs = PHP_INT_MAX;
    private static int $connectionMaxNs = 0;

    /**
     * Total SQL execution statistics.
     *
     * This is the time spent inside the DBAL driver's execute()/query()/exec()
     * call. It is NOT necessarily the time spent executing SQL on the Oracle
     * server.
     *
     * @var array<string, array{
     *     queries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * }>
     */
    private static array $executeByFile = [];

    private static int $executeQueries = 0;
    private static int $executeTotalNs = 0;
    private static int $executeMinNs = PHP_INT_MAX;
    private static int $executeMaxNs = 0;

    /**
     * SQL prepare statistics.
     *
     * @var array<string, array{
     *     queries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * }>
     */
    private static array $prepareByFile = [];

    private static int $prepareQueries = 0;
    private static int $prepareTotalNs = 0;
    private static int $prepareMinNs = PHP_INT_MAX;
    private static int $prepareMaxNs = 0;

    private static ?string $currentFile = null;

    private static bool $shutdownRegistered = false;

    /**
     * Register the shutdown handler that prints the final statistics.
     */
    public static function registerShutdownHandler(): void
    {
        if (self::$shutdownRegistered) {
            return;
        }

        self::$shutdownRegistered = true;

        register_shutdown_function(static function (): void {
            self::dump();
        });
    }

    /**
     * Set the source file of the currently running PHPUnit TestCase.
     */
    public static function setCurrentTest(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $file = $reflection->getFileName();

        self::$currentFile = $file === false ? null : $file;
    }

    /**
     * Clear the currently running TestCase.
     */
    public static function clearCurrentTest(): void
    {
        self::$currentFile = null;
    }

    /**
     * Record one successfully established Oracle connection.
     *
     * @param int $durationNs Connection establishment time in nanoseconds.
     * @param int $attempts Number of underlying connection attempts.
     */
    public static function recordConnection(
        int $durationNs,
        int $attempts
    ): void {
        ++self::$connections;
        self::$connectionAttempts += $attempts;
        self::$connectionRetries += max(0, $attempts - 1);

        self::$connectionTotalNs += $durationNs;
        self::$connectionMinNs = min(
            self::$connectionMinNs,
            $durationNs
        );
        self::$connectionMaxNs = max(
            self::$connectionMaxNs,
            $durationNs
        );

        if (self::$currentFile === null) {
            return;
        }

        $file = self::$currentFile;

        $stats = self::$connectionsByFile[$file] ?? [
            'connections' => 0,
            'attempts' => 0,
            'retries' => 0,
            'totalNs' => 0,
            'minNs' => PHP_INT_MAX,
            'maxNs' => 0,
        ];

        ++$stats['connections'];
        $stats['attempts'] += $attempts;
        $stats['retries'] += max(0, $attempts - 1);

        $stats['totalNs'] += $durationNs;
        $stats['minNs'] = min($stats['minNs'], $durationNs);
        $stats['maxNs'] = max($stats['maxNs'], $durationNs);

        self::$connectionsByFile[$file] = $stats;
    }

    /**
     * Backwards-compatible alias if the current RetryConnectionMiddleware
     * already calls record().
     */
    public static function record(
        int $durationNs,
        int $attempts
    ): void {
        self::recordConnection($durationNs, $attempts);
    }

    /**
     * Record time spent inside a DBAL execute/query/exec operation.
     *
     * @param int $durationNs Duration in nanoseconds.
     */
    public static function recordExecute(int $durationNs): void
    {
        ++self::$executeQueries;
        self::$executeTotalNs += $durationNs;
        self::$executeMinNs = min(
            self::$executeMinNs,
            $durationNs
        );
        self::$executeMaxNs = max(
            self::$executeMaxNs,
            $durationNs
        );

        self::recordByFile(
            self::$executeByFile,
            $durationNs
        );
    }

    /**
     * Record time spent preparing a DBAL statement.
     *
     * @param int $durationNs Duration in nanoseconds.
     */
    public static function recordPrepare(int $durationNs): void
    {
        ++self::$prepareQueries;
        self::$prepareTotalNs += $durationNs;
        self::$prepareMinNs = min(
            self::$prepareMinNs,
            $durationNs
        );
        self::$prepareMaxNs = max(
            self::$prepareMaxNs,
            $durationNs
        );

        self::recordByFile(
            self::$prepareByFile,
            $durationNs
        );
    }

    /**
     * @param array<string, array{
     *     queries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * }> $collection
     */
    private static function recordByFile(
        array &$collection,
        int $durationNs
    ): void {
        if (self::$currentFile === null) {
            return;
        }

        $file = self::$currentFile;

        $stats = $collection[$file] ?? [
            'queries' => 0,
            'totalNs' => 0,
            'minNs' => PHP_INT_MAX,
            'maxNs' => 0,
        ];

        ++$stats['queries'];
        $stats['totalNs'] += $durationNs;
        $stats['minNs'] = min(
            $stats['minNs'],
            $durationNs
        );
        $stats['maxNs'] = max(
            $stats['maxNs'],
            $durationNs
        );

        $collection[$file] = $stats;
    }

    /**
     * Print all collected statistics.
     */
    public static function dump(): void
    {
        self::dumpConnectionStatistics();

        self::dumpSqlStatistics(
            'Oracle PREPARE statistics',
            self::$prepareByFile,
            self::$prepareQueries,
            self::$prepareTotalNs,
            self::$prepareMinNs,
            self::$prepareMaxNs
        );

        self::dumpSqlStatistics(
            'Oracle EXECUTE statistics',
            self::$executeByFile,
            self::$executeQueries,
            self::$executeTotalNs,
            self::$executeMinNs,
            self::$executeMaxNs
        );
    }

    /**
     * Print connection statistics.
     */
    private static function dumpConnectionStatistics(): void
    {
        if (self::$connections === 0) {
            return;
        }

        echo "\n";
        echo "============================================================\n";
        echo "Oracle connection statistics\n";
        echo "============================================================\n";

        $files = self::$connectionsByFile;

        uasort(
            $files,
            static fn (array $a, array $b): int =>
                $b['totalNs'] <=> $a['totalNs']
        );

        foreach ($files as $file => $stats) {
            self::dumpConnectionStats($file, $stats);
        }

        echo "------------------------------------------------------------\n";

        self::dumpConnectionStats(
            'TOTAL',
            [
                'connections' => self::$connections,
                'attempts' => self::$connectionAttempts,
                'retries' => self::$connectionRetries,
                'totalNs' => self::$connectionTotalNs,
                'minNs' => self::$connectionMinNs,
                'maxNs' => self::$connectionMaxNs,
            ]
        );

        echo "============================================================\n";
    }

    /**
     * @param array{
     *     connections: int,
     *     attempts: int,
     *     retries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * } $stats
     */
    private static function dumpConnectionStats(
        string $name,
        array $stats
    ): void {
        $totalMs = $stats['totalNs'] / 1_000_000;
        $minMs = $stats['minNs'] / 1_000_000;
        $maxMs = $stats['maxNs'] / 1_000_000;

        $averageMs = $stats['connections'] > 0
            ? $totalMs / $stats['connections']
            : 0.0;

        echo "\n";
        echo $name . "\n";
        echo str_repeat(
            '-',
            min(60, max(1, strlen($name)))
        ) . "\n";

        echo 'connections:  '
            . number_format($stats['connections'])
            . "\n";

        echo 'attempts:     '
            . number_format($stats['attempts'])
            . "\n";

        echo 'retries:      '
            . number_format($stats['retries'])
            . "\n";

        echo 'total:        '
            . number_format($totalMs, 3)
            . " ms\n";

        echo 'average:      '
            . number_format($averageMs, 3)
            . " ms\n";

        echo 'min:          '
            . number_format($minMs, 3)
            . " ms\n";

        echo 'max:          '
            . number_format($maxMs, 3)
            . " ms\n";
    }

    /**
     * @param array<string, array{
     *     queries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * }> $byFile
     */
    private static function dumpSqlStatistics(
        string $title,
        array $byFile,
        int $queries,
        int $totalNs,
        int $minNs,
        int $maxNs
    ): void {
        if ($queries === 0) {
            return;
        }

        echo "\n";
        echo "============================================================\n";
        echo $title . "\n";
        echo "============================================================\n";

        uasort(
            $byFile,
            static fn (array $a, array $b): int =>
                $b['totalNs'] <=> $a['totalNs']
        );

        foreach ($byFile as $file => $stats) {
            self::dumpSqlStats($file, $stats);
        }

        echo "------------------------------------------------------------\n";

        self::dumpSqlStats(
            'TOTAL',
            [
                'queries' => $queries,
                'totalNs' => $totalNs,
                'minNs' => $minNs,
                'maxNs' => $maxNs,
            ]
        );

        echo "============================================================\n";
    }

    /**
     * @param array{
     *     queries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * } $stats
     */
    private static function dumpSqlStats(
        string $name,
        array $stats
    ): void {
        $totalMs = $stats['totalNs'] / 1_000_000;
        $minMs = $stats['minNs'] / 1_000_000;
        $maxMs = $stats['maxNs'] / 1_000_000;

        $averageMs = $stats['queries'] > 0
            ? $totalMs / $stats['queries']
            : 0.0;

        echo "\n";
        echo $name . "\n";
        echo str_repeat(
            '-',
            min(60, max(1, strlen($name)))
        ) . "\n";

        echo 'queries:      '
            . number_format($stats['queries'])
            . "\n";

        echo 'total:        '
            . number_format($totalMs, 3)
            . " ms\n";

        echo 'average:      '
            . number_format($averageMs, 3)
            . " ms\n";

        echo 'min:          '
            . number_format($minMs, 3)
            . " ms\n";

        echo 'max:          '
            . number_format($maxMs, 3)
            . " ms\n";
    }
}