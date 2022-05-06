<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Core\Phpunit\TestCase as BaseTestCase;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Doctrine\DBAL\Logging\SQLLogger;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\AbstractSchemaManager;

abstract class TestCase extends BaseTestCase
{
    /** @var Persistence|Persistence\Sql */
    public $db;

    /** @var bool If true, SQL queries are dumped. */
    public $debug = false;

    /** @var Migrator[] */
    private $createdMigrators = [];

    /**
     * Setup test database.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Persistence::connect($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);

        if ($this->db->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->db->connection->expr(
                'SET SESSION auto_increment_increment = 1, SESSION auto_increment_offset = 1'
            )->execute();
        }

        $this->db->connection->connection()->getConfiguration()->setSQLLogger(
            null ?? new class($this) implements SQLLogger { // @phpstan-ignore-line
                /** @var \WeakReference<TestCase> */
                private $testCaseWeakRef;

                public function __construct(TestCase $testCase)
                {
                    $this->testCaseWeakRef = \WeakReference::create($testCase);
                }

                public function startQuery($sql, array $params = null, array $types = null): void
                {
                    if (!$this->testCaseWeakRef->get()->debug) {
                        return;
                    }

                    echo "\n" . $sql . "\n" . (is_array($params) ? print_r(array_map(function ($v) {
                        if (is_string($v) && strlen($v) > 4096) {
                            $v = '*long string* (length: ' . strlen($v) . ' bytes, sha256: ' . hash('sha256', $v) . ')';
                        }

                        return $v;
                    }, $params), true) : '') . "\n\n";
                }

                public function stopQuery(): void
                {
                }
            }
        );
    }

    protected function tearDown(): void
    {
        foreach ($this->createdMigrators as $migrator) {
            foreach ($migrator->getCreatedTableNames() as $t) {
                (clone $migrator)->table($t)->dropIfExists();
            }
        }
        $this->createdMigrators = [];

        parent::tearDown();
    }

    protected function getDatabasePlatform(): AbstractPlatform
    {
        return $this->db->connection->getDatabasePlatform();
    }

    /**
     * @phpstan-return AbstractSchemaManager<AbstractPlatform>
     */
    protected function createSchemaManager(): AbstractSchemaManager
    {
        return $this->db->connection->connection()->createSchemaManager();
    }

    private function convertSqlFromSqlite(string $sql): string
    {
        $platform = $this->getDatabasePlatform();

        $convertedSql = preg_replace_callback(
            '~\'(?:[^\'\\\\]+|\\\\.)*\'|"(?:[^"\\\\]+|\\\\.)*"|:(\w+)~s',
            function ($matches) use ($platform) {
                if (isset($matches[1])) {
                    return ':' . ($platform instanceof OraclePlatform ? 'xxaaa' : '') . $matches[1];
                }

                $str = substr(preg_replace('~\\\\(.)~s', '$1', $matches[0]), 1, -1);
                if (substr($matches[0], 0, 1) === '"') {
                    return $platform->quoteSingleIdentifier($str);
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

    /**
     * Sets database into a specific test.
     */
    public function setDb(array $dbData, bool $importData = true): void
    {
        // create tables
        foreach ($dbData as $tableName => $data) {
            $migrator = $this->createMigrator()->table($tableName);

            // drop table if already created but only if it was created during this test
            foreach ($this->createdMigrators as $migr) {
                if ($migr->connection === $this->db->connection) {
                    foreach ($migr->getCreatedTableNames() as $t) {
                        if ($t === $tableName) {
                            $migrator->drop();

                            break 2;
                        }
                    }
                }
            }

            $firstRow = current($data);
            $idColumnName = null;
            if ($firstRow) {
                $idColumnName = isset($firstRow['_id']) ? '_id' : 'id';
                $migrator->id($idColumnName);

                foreach ($firstRow as $field => $row) {
                    if ($field === $idColumnName) {
                        continue;
                    }

                    if (is_int($row)) {
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

                        $query->mode('insert')->execute();
                    }
                });
            }
        }
    }

    /**
     * Return database data.
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
}
