<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Core\Phpunit\TestCase as BaseTestCase;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

abstract class TestCase extends BaseTestCase
{
    /** @var Persistence|Persistence\Sql */
    public $db;

    /** @var bool If true, SQL queries are dumped. */
    public $debug = false;

    /** @var array<int, Migrator> */
    private array $createdMigrators = [];

    /**
     * @return static|null
     */
    public static function getTestFromBacktrace()
    {
        foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            if (($frame['object'] ?? null) instanceof static) {
                return $frame['object'];
            }
        }

        return null;
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new TestSqlPersistence();
    }

    protected function tearDown(): void
    {
        $debugOrig = $this->debug;
        try {
            $this->debug = false;
            $this->dropCreatedDb();
        } finally {
            $this->debug = $debugOrig;
        }

        parent::tearDown();
    }

    protected function getConnection(): Persistence\Sql\Connection
    {
        return $this->db->getConnection(); // @phpstan-ignore-line
    }

    protected function getDatabasePlatform(): AbstractPlatform
    {
        return $this->getConnection()->getDatabasePlatform();
    }

    /**
     * @param array<int|string, scalar|null>      $params
     * @param array<int|string, ParameterType::*> $types
     */
    protected function logQuery(string $sql, array $params, array $types): void
    {
        if (!$this->debug) {
            return;
        }

        $exprNoRender = new class($sql, $params) extends Expression {
            public function render(): array
            {
                return [$this->template, $this->args['custom']];
            }
        };
        $sqlWithParams = $exprNoRender->getDebugQuery();
        if (substr($sqlWithParams, -1) !== ';') {
            $sqlWithParams .= ';';
        }

        echo "\n" . $sqlWithParams . "\n\n";
    }

    private function convertSqlFromSqlite(string $sql): string
    {
        $platform = $this->getDatabasePlatform();

        $convertedSql = preg_replace_callback(
            '~\'(?:[^\'\\\\]+|\\\\.)*+\'|`(?:[^`\\\\]+|\\\\.)*+`|:(\w+)~s',
            static function ($matches) use ($platform) {
                if (isset($matches[1])) {
                    return ':' . ($platform instanceof OraclePlatform ? 'xxaaa' : '') . $matches[1];
                }

                $str = substr(preg_replace('~\\\\(.)~s', '$1', $matches[0]), 1, -1);
                if (substr($matches[0], 0, 1) === '`') {
                    return $platform->quoteSingleIdentifier($str);
                }

                return ($platform instanceof SQLServerPlatform ? 'N' : '') . $platform->quoteStringLiteral($str);
            },
            $sql
        );

        if ($platform instanceof SQLitePlatform && $convertedSql !== $sql) {
            self::assertSame($sql, $convertedSql);
        }

        return $convertedSql;
    }

    protected function assertSameSql(string $expectedSqliteSql, string $actualSql, string $message = ''): void
    {
        self::assertSame($this->convertSqlFromSqlite($expectedSqliteSql), $actualSql, $message);
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    private static function compareExportUnorderedValue($a, $b): int
    {
        if ($a === $b) {
            return 0;
        }

        $cmp = gettype($a) <=> gettype($b);
        if ($cmp !== 0) {
            return $cmp;
        }

        if (is_object($a)) {
            $cmp = gettype($a) <=> gettype($b);
            if ($cmp !== 0) {
                return $cmp;
            }

            if ($a instanceof \DateTimeInterface) {
                $format = 'Y-m-d H:i:s.u e I Z';

                return $a->format($format) <=> $b->format($format);
            }
        }

        if (is_array($a) && count($a) === count($b)) {
            $is2d = true;
            foreach ($a as $v) {
                if (!is_array($v)) {
                    $is2d = false;

                    break;
                }
            }
            if ($is2d) {
                foreach ($b as $v) {
                    if (!is_array($v)) {
                        $is2d = false;

                        break;
                    }
                }
            }

            if ($is2d) {
                if (array_is_list($a) && array_is_list($b)) {
                    usort($a, static fn ($a, $b) => self::compareExportUnorderedValue($a, $b));
                    usort($b, static fn ($a, $b) => self::compareExportUnorderedValue($a, $b));
                } else {
                    uasort($a, static fn ($a, $b) => self::compareExportUnorderedValue($a, $b));
                    uasort($b, static fn ($a, $b) => self::compareExportUnorderedValue($a, $b));
                }
            }

            if (array_keys($a) === array_keys($b)) {
                foreach ($a as $k => $v) {
                    $cmp = self::compareExportUnorderedValue($v, $b[$k]);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }

                return 0;
            }
        }

        return $a <=> $b;
    }

    /**
     * Same as self::assertSame() except:
     * - 2D arrays (rows) are recursively compared without any order
     * - objects implementing DateTimeInterface are compared by formatted output.
     *
     * @param array<mixed, mixed> $expected
     * @param array<mixed, mixed> $actual
     */
    protected static function assertSameExportUnordered(array $expected, array $actual, string $message = ''): void
    {
        if (self::compareExportUnorderedValue($expected, $actual) === 0) {
            self::assertTrue(true); // @phpstan-ignore-line

            return;
        }

        self::assertSame($expected, $actual, $message);
    }

    public function createMigrator(Model $model = null): Migrator
    {
        $migrator = new Migrator($model ?? $this->db);
        $this->createdMigrators[] = $migrator;

        return $migrator;
    }

    /**
     * @param array<string, array<int|'_', array<string, mixed>>> $dbData
     */
    public function setDb(array $dbData, bool $importData = true): void
    {
        foreach ($dbData as $tableName => $data) {
            $migrator = $this->createMigrator()->table($tableName);

            $fieldTypes = [];
            foreach ($data as $row) {
                foreach ($row as $k => $v) {
                    if (isset($fieldTypes[$k])) {
                        continue;
                    }

                    if (is_bool($v)) {
                        $fieldType = 'boolean';
                    } elseif (is_int($v)) {
                        $fieldType = 'integer';
                    } elseif (is_float($v)) {
                        $fieldType = 'float';
                    } elseif ($v instanceof \DateTimeInterface) {
                        $fieldType = 'datetime';
                    } elseif ($v !== null) {
                        $fieldType = 'string';
                    } else {
                        $fieldType = null;
                    }

                    $fieldTypes[$k] = $fieldType;
                }
            }
            foreach ($fieldTypes as $k => $fieldType) {
                if ($fieldType === null) {
                    $fieldTypes[$k] = 'string';
                }
            }
            $idColumnName = isset($fieldTypes['_id']) ? '_id' : 'id';

            // create table
            $migrator->id($idColumnName, isset($fieldTypes[$idColumnName]) ? ['type' => $fieldTypes[$idColumnName]] : []);
            foreach ($fieldTypes as $k => $fieldType) {
                if ($k === $idColumnName) {
                    continue;
                }

                $migrator->field($k, ['type' => $fieldType]);
            }
            $migrator->create();

            // import data
            if ($importData) {
                $this->db->atomic(function () use ($tableName, $data, $idColumnName) {
                    $hasId = array_key_first($data) !== 0;

                    foreach ($data as $id => $row) {
                        $query = $this->db->dsql();
                        if ($id === '_') {
                            continue;
                        }

                        $query->table($tableName);
                        $query->setMulti($row);

                        if (!isset($row[$idColumnName]) && $hasId) {
                            $query->set($idColumnName, $id);
                        }

                        $query->mode('insert')->executeStatement();
                    }
                });
            }
        }
    }

    /**
     * @param array<int, string>|null $tableNames
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getDb(array $tableNames = null, bool $noId = false): array
    {
        if ($tableNames === null) {
            $tableNames = [];
            foreach ($this->createdMigrators as $migrator) {
                foreach ($migrator->getCreatedTableNames() as $t) {
                    $tableNames[$t] = $t;
                }
            }
            $tableNames = array_values($tableNames);
        }

        $resAll = [];
        foreach ($tableNames as $table) {
            $query = $this->db->dsql();
            $rows = $query->table($table)->getRows();

            $res = [];
            $idColumnName = null;
            foreach ($rows as $row) {
                if ($idColumnName === null) {
                    $idColumnName = isset($row['_id']) ? '_id' : 'id';
                }

                foreach ($row as $k => $v) {
                    if (preg_match('~(?:^|_)id$~', $k) && $v === (string) (int) $v) {
                        $row[$k] = (int) $v;
                    }
                }

                if ($noId) {
                    unset($row[$idColumnName]);
                    $res[] = $row;
                } else {
                    $res[$row[$idColumnName]] = $row;
                }
            }

            if (!$noId) {
                ksort($res);
            }

            $resAll[$table] = $res;
        }

        return $resAll;
    }

    public function dropCreatedDb(): void
    {
        while (count($this->createdMigrators) > 0) {
            $migrator = array_pop($this->createdMigrators);
            foreach ($migrator->getCreatedTableNames() as $t) {
                (clone $migrator)->table($t)->dropIfExists(true);
            }
        }
    }

    protected function markTestIncompleteOnMySQL56PlatformAsCreateUniqueStringIndexHasLengthLimit(): void
    {
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $serverVersion = $this->getConnection()->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
            if (preg_match('~^5\.6~', $serverVersion)) {
                self::markTestIncomplete('TODO MySQL 5.6: Unique key exceed max key (767 bytes) length');
            }
        }
    }
}
