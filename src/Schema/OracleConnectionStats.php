<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

/**
 * Collects Oracle connection establishment statistics during PHPUnit tests.
 *
 * Statistics are aggregated by the source file of the current TestCase and
 * globally for the whole PHP process.
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
    private static array $byFile = [];

    private static int $connections = 0;
    private static int $attempts = 0;
    private static int $retries = 0;
    private static int $totalNs = 0;
    private static int $minNs = PHP_INT_MAX;
    private static int $maxNs = 0;

    private static ?string $currentFile = null;

    private static bool $shutdownRegistered = false;

    /**
     * @var array<string, array{
     *     queries: int,
     *     totalNs: int,
     *     minNs: int,
     *     maxNs: int
     * }>
     */
    private static array $sqlByFile = [];

    private static int $sqlQueries = 0;
    private static int $sqlTotalNs = 0;
    private static int $sqlMinNs = PHP_INT_MAX;
    private static int $sqlMaxNs = 0;

    /**
     * Register the PHP shutdown handler.
     *
     * The report is printed once after PHPUnit has finished all tests.
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
     * Set the source file of the currently executing TestCase.
     */
    public static function setCurrentTest(string $class): void
    {
        $reflection = new \ReflectionClass($class);
        $file = $reflection->getFileName();

        self::$currentFile = $file === false ? null : $file;
    }

    /**
     * Clear the current TestCase.
     */
    public static function clearCurrentTest(): void
    {
        self::$currentFile = null;
    }

    /**
     * Record one successfully established Oracle connection.
     *
     * @param int $durationNs Connection establishment duration in nanoseconds.
     * @param int $attempts Number of calls to the underlying connect() method.
     */
    public static function record(int $durationNs, int $attempts): void
    {
        ++self::$connections;
        self::$attempts += $attempts;
        self::$retries += max(0, $attempts - 1);

        self::$totalNs += $durationNs;
        self::$minNs = min(self::$minNs, $durationNs);
        self::$maxNs = max(self::$maxNs, $durationNs);

        if (self::$currentFile === null) {
            return;
        }

        $file = self::$currentFile;

        $stats = self::$byFile[$file] ?? [
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

        self::$byFile[$file] = $stats;
    }

    public static function recordQuery(int $durationNs): void
    {
        ++self::$sqlQueries;
        self::$sqlTotalNs += $durationNs;
        self::$sqlMinNs = min(self::$sqlMinNs, $durationNs);
        self::$sqlMaxNs = max(self::$sqlMaxNs, $durationNs);

        if (self::$currentFile === null) {
            return;
        }

        $file = self::$currentFile;

        $stats = self::$sqlByFile[$file] ?? [
            'queries' => 0,
            'totalNs' => 0,
            'minNs' => PHP_INT_MAX,
            'maxNs' => 0,
        ];

        ++$stats['queries'];
        $stats['totalNs'] += $durationNs;
        $stats['minNs'] = min($stats['minNs'], $durationNs);
        $stats['maxNs'] = max($stats['maxNs'], $durationNs);

        self::$sqlByFile[$file] = $stats;
    }

    /**
     * Print the collected statistics.
     */
    public static function dump(): void
    {
        if (self::$connections === 0) {
            return;
        }

        echo "\n";
        echo "============================================================\n";
        echo "Oracle connection statistics\n";
        echo "============================================================\n";

        $files = self::$byFile;

        uasort(
            $files,
            static function (array $a, array $b): int {
                return $b['totalNs'] <=> $a['totalNs'];
            }
        );

        foreach ($files as $file => $stats) {
            self::dumpStats($file, $stats);
        }

        echo "------------------------------------------------------------\n";

        self::dumpStats(
            'TOTAL',
            [
                'connections' => self::$connections,
                'attempts' => self::$attempts,
                'retries' => self::$retries,
                'totalNs' => self::$totalNs,
                'minNs' => self::$minNs,
                'maxNs' => self::$maxNs,
            ]
        );

        echo "============================================================\n";

        echo "\n";
        echo "============================================================\n";
        echo "Oracle SQL execution statistics\n";
        echo "============================================================\n";

        $files = self::$sqlByFile;

        uasort(
            $files,
            static function (array $a, array $b): int {
                return $b['totalNs'] <=> $a['totalNs'];
            }
        );

        foreach ($files as $file => $stats) {
            self::dumpQueryStats($file, $stats);
        }

        echo "------------------------------------------------------------\n";

        self::dumpQueryStats(
            'TOTAL',
            [
                'queries' => self::$sqlQueries,
                'totalNs' => self::$sqlTotalNs,
                'minNs' => self::$sqlMinNs,
                'maxNs' => self::$sqlMaxNs,
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
    private static function dumpQueryStats(string $name, array $stats): void
    {
        $totalMs = $stats['totalNs'] / 1_000_000;
        $minMs = $stats['minNs'] / 1_000_000;
        $maxMs = $stats['maxNs'] / 1_000_000;

        $averageMs = $stats['queries'] > 0
            ? $totalMs / $stats['queries']
            : 0.0;

        echo "\n";
        echo $name . "\n";
        echo str_repeat('-', min(60, max(1, strlen($name)))) . "\n";
        echo 'queries:      ' . number_format($stats['queries']) . "\n";
        echo 'total:        ' . number_format($totalMs, 3) . " ms\n";
        echo 'average:      ' . number_format($averageMs, 3) . " ms\n";
        echo 'min:          ' . number_format($minMs, 3) . " ms\n";
        echo 'max:          ' . number_format($maxMs, 3) . " ms\n";
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
    private static function dumpStats(string $name, array $stats): void
    {
        $totalMs = $stats['totalNs'] / 1_000_000;
        $minMs = $stats['minNs'] / 1_000_000;
        $maxMs = $stats['maxNs'] / 1_000_000;

        $averageMs = $stats['connections'] > 0
            ? $totalMs / $stats['connections']
            : 0.0;

        echo "\n";
        echo $name . "\n";
        echo str_repeat('-', min(60, max(1, strlen($name)))) . "\n";
        echo 'connections:  ' . number_format($stats['connections']) . "\n";
        echo 'attempts:     ' . number_format($stats['attempts']) . "\n";
        echo 'retries:      ' . number_format($stats['retries']) . "\n";
        echo 'total:        ' . number_format($totalMs, 3) . " ms\n";
        echo 'average:      ' . number_format($averageMs, 3) . " ms\n";
        echo 'min:          ' . number_format($minMs, 3) . " ms\n";
        echo 'max:          ' . number_format($maxMs, 3) . " ms\n";
    }
}
