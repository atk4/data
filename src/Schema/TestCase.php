<?php

declare(strict_types=1);

namespace Atk4\Data\Schema;

use Atk4\Core\Phpunit\TestCase as BaseTestCase;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Mysql\Connection as MysqlConnection;
use Atk4\Data\Persistence\Sql\RawExpression;
use Atk4\Data\Persistence\Sql\Sqlite\Expression as SqliteExpression;
use Atk4\Data\Reference;
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

    /** @var list<Migrator> */
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

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new TestSqlPersistence();
    }

    #[\Override]
    protected function tearDown(): void
    {
        $debugOrig = $this->debug;
        try {
            $this->debug = false;
            $this->dropCreatedDb();
        } finally {
            $this->debug = $debugOrig;
        }

        if (\PHP_VERSION_ID < 80300) {
            // workaround https://github.com/php/php-src/issues/10043
            \Closure::bind(static function () {
                if ((Reference::$analysingClosureMap ?? null) !== null) {
                    Reference::$analysingClosureMap = new Reference\WeakAnalysingMap();
                }
                if ((Reference::$analysingTheirModelMap ?? null) !== null) {
                    Reference::$analysingTheirModelMap = new Reference\WeakAnalysingMap();
                }
            }, null, Reference::class)();
        }

        parent::tearDown();
    }

    protected function getConnection(): Persistence\Sql\Connection
    {
        return $this->db->getConnection(); // @phpstan-ignore method.notFound
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

        // needed for \Atk4\Data\Persistence\Sql\*\ExpressionTrait::updateRenderBeforeExecute() fixes
        $i = 0;
        $quotedTokenRegex = $this->getConnection()->expr()::QUOTED_TOKEN_REGEX;
        $sql = preg_replace_callback(
            '~' . $quotedTokenRegex . '\K|(\?)|cast\((\?|:\w+) as (BOOLEAN|INTEGER|BIGINT|DOUBLE PRECISION|BINARY_DOUBLE|citext|bytea|unknown)\)|\((\?|:\w+) \+ 0\.00\)~',
            static function ($matches) use (&$types, &$params, &$i) {
                if ($matches[0] === '') {
                    return '';
                }

                if ($matches[1] === '?') {
                    ++$i;

                    return $matches[0];
                }

                $k = isset($matches[4])
                    ? ($matches[4] === '?' ? ++$i : $matches[4])
                    : ($matches[2] === '?' ? ++$i : $matches[2]);

                if ($matches[3] === 'BOOLEAN' && ($types[$k] === ParameterType::BOOLEAN || $types[$k] === ParameterType::INTEGER)
                    && (is_bool($params[$k]) || $params[$k] === '0' || $params[$k] === '1')
                ) {
                    $types[$k] = ParameterType::BOOLEAN;
                    $params[$k] = (bool) $params[$k];

                    return $matches[4] ?? $matches[2];
                } elseif (($matches[3] === 'INTEGER' || $matches[3] === 'BIGINT') && $types[$k] === ParameterType::INTEGER && is_int($params[$k])) {
                    return $matches[4] ?? $matches[2];
                } elseif (($matches[3] === 'DOUBLE PRECISION' || $matches[3] === 'BINARY_DOUBLE' || isset($matches[4]))
                    && $types[$k] === ParameterType::STRING && is_string($params[$k]) && is_numeric($params[$k])
                ) {
                    // $types[$k] = ParameterType::FLOAT; is not supported yet by DBAL
                    $params[$k] = (float) $params[$k];

                    return $matches[4] ?? $matches[2];
                } elseif (($matches[3] === 'citext' || $matches[3] === 'bytea') && is_string($params[$k])) {
                    return $matches[2];
                } elseif ($matches[3] === 'unknown' && $params[$k] === null) {
                    return $matches[2];
                }

                return $matches[0];
            },
            $sql
        );

        $sqlWithParams = (new RawExpression([
            'template' => $sql,
            'connection' => $this->getConnection(),
        ], $params))->getDebugQuery();

        if (substr($sqlWithParams, -1) !== ';') {
            $sqlWithParams .= ';';
        }

        echo "\n" . $sqlWithParams . "\n\n";
    }

    private function convertSqlFromSqlite(string $sql): string
    {
        $platform = $this->getDatabasePlatform();

        $convertedSql = preg_replace_callback(
            '~(?![\'`])' . SqliteExpression::QUOTED_TOKEN_REGEX . '\K|' . SqliteExpression::QUOTED_TOKEN_REGEX . '|:(\w+)~',
            static function ($matches) use ($platform) {
                if ($matches[0] === '') {
                    return '';
                }

                if (isset($matches[1])) {
                    return ':' . ($platform instanceof OraclePlatform ? 'xxaaa' : '') . $matches[1];
                }

                $str = substr(preg_replace('~\\\(.)~s', '$1', $matches[0]), 1, -1);
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
        // remove once SQLite affinity of expressions is fixed natively
        // related with Atk4\Data\Persistence\Sql\Sqlite\Query::_renderConditionBinary() fix
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            do {
                $actualSqlPrev = $actualSql;
                $actualSql = preg_replace('~case\s+when typeof\((.+?)\) in \(\'integer\', \'real\'\) then\s+cast\(\1 as numeric\) (.{1,20}?) (.+?)\s+else\s+\1 \2 \3\s+end~s', '$1 $2 $3', $actualSql);
                $actualSql = preg_replace('~case\s+when typeof\((.+?)\) in \(\'integer\', \'real\'\) then\s+(.+?) (.{1,20}?) cast\(\1 as numeric\)\s+else\s+\2 \3 \1\s+end~s', '$2 $3 $1', $actualSql);
            } while ($actualSql !== $actualSqlPrev);
            do {
                $actualSqlPrev = $actualSql;
                $actualSql = preg_replace('~\(select `__atk4_affinity_left__` (.{1,20}?) `__atk4_affinity_right__` from \(select (.+?) `__atk4_affinity_left__`, (.+?) `__atk4_affinity_right__`\) `__atk4_affinity_tmp__`\)~s', '$2 $1 $3', $actualSql);
                $actualSql = preg_replace('~\(select `__atk4_affinity_left__` (.{1,20}?) (.+?) from \(select (.+?) `__atk4_affinity_left__`\) `__atk4_affinity_tmp__`\)~s', '$3 $1 $2', $actualSql);
            } while ($actualSql !== $actualSqlPrev);
        }

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
            self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType

            return;
        }

        self::assertSame($expected, $actual, $message);
    }

    public function createMigrator(?Model $model = null): Migrator
    {
        $migrator = new Migrator($model ?? $this->db);
        $this->createdMigrators[] = $migrator;

        return $migrator;
    }

    /**
     * @param array<string, array<int|'_types', array<string, mixed>>> $dbData
     */
    public function setDb(array $dbData, bool $importData = true): void
    {
        foreach ($dbData as $tableName => $data) {
            $idField = $data['_types']['_idField'] ?? 'id';
            unset($data['_types']['_idField']);

            $fieldTypes = $data['_types'] ?? [];
            unset($data['_types']);
            foreach ($data as $row) {
                foreach ($row as $k => $v) {
                    if (isset($fieldTypes[$k])) {
                        continue;
                    }

                    if (is_bool($v)) {
                        $type = 'boolean';
                    } elseif (is_int($v)) {
                        $type = 'integer';
                    } elseif (is_float($v)) {
                        $type = 'float';
                    } elseif ($v !== null) {
                        $type = 'string';
                    } else {
                        $type = null;
                    }

                    $fieldTypes[$k] = $type;
                }
            }
            foreach ($fieldTypes as $k => $type) {
                if ($type === null) {
                    $fieldTypes[$k] = 'string';
                }
            }
            if (!isset($fieldTypes[$idField])) {
                $fieldTypes = array_merge([$idField => 'integer'], $fieldTypes);
            }

            $model = new Model(null, ['table' => $tableName, 'idField' => $idField]);
            foreach ($fieldTypes as $k => $type) {
                $model->addField($k, ['type' => $type]);
            }
            $model->setPersistence($this->db);

            // create table
            $migrator = $this->createMigrator($model);
            $migrator->create();

            // import data
            if ($importData) {
                if (array_key_first($data) !== 0) {
                    foreach ($data as $id => $row) {
                        if (!isset($row[$idField])) {
                            $data[$id][$idField] = $id;
                        }
                    }
                }

                $model->import($data);
            }
        }
    }

    /**
     * @param list<string>|null $tableNames
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function getDb(?array $tableNames = null, bool $noId = false): array
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
            $model = $this->createMigrator()->introspectTableToModel($table);
            $model->setPersistence($this->db);

            if (!$noId) {
                $model->setOrder($model->idField);
            }

            $data = $noId
                ? $model->export(array_diff(array_keys($model->getFields()), [$model->idField]))
                : $model->export(null, $model->idField);

            $resAll[$table] = $data;
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
        if ($this->getDatabasePlatform() instanceof MySQLPlatform
            && !MysqlConnection::isServerMariaDb($this->getConnection())
            && MysqlConnection::getServerMinorVersion($this->getConnection()) < 570
        ) {
            self::markTestIncomplete('TODO MySQL 5.6: Unique key exceed max key (767 bytes) length');
        }
    }

    protected function markTestIncompleteOnMySQL8xPlatformAsBinaryLikeIsBroken(bool $isBinary): void
    {
        if ($this->getDatabasePlatform() instanceof MySQLPlatform && $isBinary
            && !MysqlConnection::isServerMariaDb($this->getConnection())
            && MysqlConnection::getServerMinorVersion($this->getConnection()) >= 800
        ) {
            // MySQL v8.0.22 and higher throws SQLSTATE[HY000]: General error: 3995 Character set 'binary'
            // cannot be used in conjunction with 'utf8mb4_0900_ai_ci' in call to regexp_like.
            // https://github.com/mysql/mysql-server/blob/72136a6d15/sql/item_regexp_func.cc#L115-L120
            // https://dbfiddle.uk/9SA-omyF
            self::markTestIncomplete('MySQL 8.x has broken binary LIKE support');
        }
    }
}
