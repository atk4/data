<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Core\Phpunit\TestCase as BaseTestCase;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

abstract class TestCase extends BaseTestCase
{
    /** @var Persistence|Persistence\Sql */
    public $db;

    /** @var bool If true, SQL queries are dumped. */
    public $debug = false;

    /** @var Migrator[] */
    private $createdMigrators = [];

    /**
     * @return static|null
     */
    public static function getTestFromBacktrace()
    {
        foreach (debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS | \DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
            if (($frame['object'] ?? null) instanceof static) {
                return $frame['object']; // @phpstan-ignore-line https://github.com/phpstan/phpstan/issues/7639
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
            function ($matches) use ($platform) {
                if (isset($matches[1])) {
                    return ':' . ($platform instanceof OraclePlatform ? 'xxaaa' : '') . $matches[1];
                }

                $str = substr(preg_replace('~\\\\(.)~s', '$1', $matches[0]), 1, -1);
                if (substr($matches[0], 0, 1) === '`') {
                    return $this->getConnection()->expr('{}', [$str])->render()[0];
                }

                return ($platform instanceof SQLServerPlatform ? 'N' : '') . $platform->quoteStringLiteral($str);
            },
            $sql
        );

        if ($platform instanceof SqlitePlatform && $convertedSql !== $sql) {
            $this->assertSame($sql, $convertedSql);
        }

        return $convertedSql;
    }

    protected function assertSameSql(string $expectedSqliteSql, string $actualSql, string $message = ''): void
    {
        $this->assertSame($this->convertSqlFromSqlite($expectedSqliteSql), $actualSql, $message);
    }

    /**
     * @param mixed $a
     * @param mixed $b
     */
    private function compareExportUnorderedValue($a, $b): int
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
                    usort($a, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                    usort($b, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                } else {
                    uasort($a, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                    uasort($b, fn ($a, $b) => $this->compareExportUnorderedValue($a, $b));
                }
            }

            if (array_keys($a) === array_keys($b)) {
                foreach ($a as $k => $v) {
                    $cmp = $this->compareExportUnorderedValue($v, $b[$k]);
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
     */
    protected function assertSameExportUnordered(array $expected, array $actual, string $message = ''): void
    {
        if ($this->compareExportUnorderedValue($expected, $actual) === 0) {
            $this->assertTrue(true);

            return;
        }

        $this->assertSame($expected, $actual, $message);
    }

    public function createMigrator(Model $model = null): Migrator
    {
        $migrator = new Migrator($model ?: $this->db);
        $this->createdMigrators[] = $migrator;

        return $migrator;
    }

    public function setDb(array $dbData, bool $importData = true): void
    {
        foreach ($dbData as $tableName => $data) {
            $migrator = $this->createMigrator()->table($tableName);

            // create table
            $firstRow = current($data);
            $idColumnName = null;
            if ($firstRow) {
                $idColumnName = isset($firstRow['_id']) ? '_id' : 'id';
                $migrator->id($idColumnName);

                foreach ($firstRow as $field => $row) {
                    if ($field === $idColumnName) {
                        continue;
                    }

                    if (is_bool($row)) {
                        $fieldType = 'boolean';
                    } elseif (is_int($row)) {
                        $fieldType = 'integer';
                    } elseif (is_float($row)) {
                        $fieldType = 'float';
                    } elseif ($row instanceof \DateTimeInterface) {
                        $fieldType = 'datetime';
                    } else {
                        $fieldType = 'string';
                    }

                    $migrator->field($field, ['type' => $fieldType]);
                }

                $migrator->create();
            }

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

                if ($noId) {
                    unset($row[$idColumnName]);
                    $res[] = $row;
                } else {
                    $res[$row[$idColumnName]] = $row;
                }
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

    public function markTestIncompleteWhenCreateUniqueIndexIsNotSupportedByPlatform(): void
    {
        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            // https://github.com/doctrine/dbal/issues/5507
            $this->markTestIncomplete('TODO MSSQL: DBAL must setup unique index without WHERE clause');
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            // https://github.com/doctrine/dbal/issues/5508
            $this->markTestIncomplete('TODO Oracle: DBAL must setup unique index on table column too');
        }
    }
}
