<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\ExecuteException;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Mysql\Connection as MysqlConnection;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Persistence\Sql\Sqlite\Connection as SqliteConnection;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use PHPUnit\Framework\Attributes\DataProvider;

class SelectTest extends TestCase
{
    protected function setupTables(): void
    {
        $model = new Model($this->db, ['table' => 'employee']);
        $model->addField('name');
        $model->addField('surname');
        $model->addField('retired', ['type' => 'boolean']);

        $this->createMigrator($model)->create();

        $model->import([
            ['id' => 1, 'name' => 'Oliver', 'surname' => 'Smith', 'retired' => false],
            ['id' => 2, 'name' => 'Jack', 'surname' => 'Williams', 'retired' => true],
            ['id' => 3, 'name' => 'Harry', 'surname' => 'Taylor', 'retired' => true],
            ['id' => 4, 'name' => 'Charlie', 'surname' => 'Lee', 'retired' => false],
        ]);
    }

    /**
     * @param string|Expression                 $table
     * @param ($table is null ? never : string) $alias
     */
    protected function q($table = null, ?string $alias = null): Query
    {
        $q = $this->getConnection()->dsql();
        if ($table !== null) {
            $q->table($table, $alias);
        }

        return $q;
    }

    /**
     * @param string|array<string, mixed> $template
     * @param array<int|string, mixed>    $arguments
     */
    protected function e($template = [], array $arguments = []): Expression
    {
        return $this->getConnection()->expr($template, $arguments);
    }

    public function testBasicQueries(): void
    {
        $this->setupTables();

        self::assertCount(4, $this->q('employee')->getRows());

        self::assertSame(
            ['name' => 'Oliver', 'surname' => 'Smith'],
            $this->q('employee')->field('name')->field('surname')->order('id')->getRow()
        );

        self::assertSameExportUnordered(
            [['surname' => 'Williams'], ['surname' => 'Taylor']],
            $this->q('employee')->field('surname')->where('retired', true)->getRows()
        );

        self::assertSame(
            '4',
            $this->q()->field($this->e('2 + 2'))->getOne()
        );

        self::assertSame(
            '4',
            $this->q('employee')->field($this->e('count(*)'))->getOne()
        );

        $names = [];
        foreach ($this->q('employee')->order('name')->where('retired', false)->getRowsIterator() as $row) {
            $names[] = $row['name'];
        }

        self::assertSame(
            ['Charlie', 'Oliver'],
            $names
        );

        self::assertSame(
            [['now' => '4']],
            $this->q()->field($this->e('2 + 2'), 'now')->getRows()
        );

        // PostgreSQL needs to have values cast, to make the query work.
        // But CAST(.. AS int) does not work in Mysql. So we use two different tests..
        // (CAST(.. AS int) will work on MariaDB, whereas Mysql needs it to be CAST(.. AS signed))
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSame(
                [['now' => '6']],
                $this->q()->field($this->e('CAST([] AS int) + CAST([] AS int)', [3, 3]), 'now')->getRows()
            );
        } else {
            self::{'assertEquals'}(
                [['now' => 6]],
                $this->q()->field($this->e('[] + []', [3, 3]), 'now')->getRows()
            );
        }

        self::assertSame(
            '5',
            $this->q()->field($this->e('COALESCE([], \'5\')', [null]), 'null_test')->getOne()
        );
    }

    public function testExpression(): void
    {
        // PostgreSQL, at least versions before 10, needs to have the string cast to the correct datatype.
        // But using CAST(.. AS CHAR) will return a single character on PostgreSQL, but the entire string on MySQL.
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSame(
                'foo',
                $this->e('select CAST([] AS VARCHAR)', ['foo'])->getOne()
            );
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame(
                'foo',
                $this->e('select CAST([] AS VARCHAR2(100)) FROM DUAL', ['foo'])->getOne()
            );
        } else {
            self::assertSame(
                'foo',
                $this->e('select CAST([] AS CHAR)', ['foo'])->getOne()
            );
        }
    }

    /**
     * @dataProvider provideSelectUnionBindLongStringCases
     */
    #[DataProvider('provideSelectUnionBindLongStringCases')]
    public function testSelectUnionBindLongString(int $length): void
    {
        $str = str_repeat('x', $length);
        $str2 = 'y' . $str;

        $tableExpr = $this->e(
            implode(' union all ', array_fill(0, 2, '[]')),
            array_map(function ($v) {
                $q = $this->q()->field($this->e('[]', [$v]), 'v');
                $q->wrapInParentheses = false;

                return $q;
            }, [$str, $str2])
        );
        $tableExpr->wrapInParentheses = true;

        $res = $this->q()
            ->field('v')
            ->table($tableExpr, 't')
            ->getRows();

        self::assertSame([
            ['v' => $str],
            ['v' => $str2],
        ], $res);
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideSelectUnionBindLongStringCases(): iterable
    {
        yield [64 * 1024 - 2];
        yield [64 * 1024 - 1];
        yield [64 * 1024];
        yield [64 * 1024 + 1];
        yield [256 * 1024];
    }

    public function testOtherQueries(): void
    {
        $this->setupTables();

        // truncate
        $this->q('employee')->mode('truncate')->executeStatement();
        self::assertSame(
            '0',
            $this->q('employee')->field($this->e('count(*)'))->getOne()
        );

        // insert
        $this->q('employee')
            ->setMulti(['id' => 1, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
            ->mode('insert')->executeStatement();
        $this->q('employee')
            ->setMulti(['id' => 2, 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
            ->mode('insert')->executeStatement();
        self::assertSame([
            ['id' => '1', 'name' => 'John'],
            ['id' => '2', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->order('id')->getRows());

        // update
        $this->q('employee')
            ->where('name', 'John')
            ->set('name', 'Johnny')
            ->mode('update')->executeStatement();
        self::assertSame([
            ['id' => '1', 'name' => 'Johnny'],
            ['id' => '2', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->order('id')->getRows());

        // replace
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof SQLServerPlatform || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->q('employee')
                ->setMulti(['name' => 'Peter', 'surname' => 'Doe', 'retired' => true])
                ->where('id', 1)
                ->mode('update')->executeStatement();
        } else {
            $this->q('employee')
                ->setMulti(['id' => 1, 'name' => 'Peter', 'surname' => 'Doe', 'retired' => true])
                ->mode('replace')->executeStatement();
        }

        // In SQLite replace is just like insert, it just checks if there is
        // duplicate key and if it is it deletes the row, and inserts the new
        // one, otherwise it just inserts.
        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'Peter'],
            ['id' => '2', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->getRows());

        // delete
        $this->q('employee')
            ->where('retired', true)
            ->mode('delete')->executeStatement();
        self::assertSame([
            ['id' => '2', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->getRows());
    }

    public function testFxConcat(): void
    {
        $parts = [];
        for ($i = 0; $i < 50; ++$i) {
            $parts[] = '_' . $i;
        }

        self::assertSame(
            implode('', $parts),
            $this->q()
                ->field($this->q()->fxConcat(...$parts))
                ->getOne()
        );
    }

    public function testFxJsonArrayRender(): void
    {
        $expr = $this->q()->fxJsonArray([$this->e('{} + []', ['u', 10])]);

        $makeReplaceControlCharsFx = static function ($v) {
            $makeReplaceFx = static function ($v, $i) {
                return 'replace(' . $v . ', \'' . chr($i) . '\', \'\u' . str_pad(dechex($i), 4, '0', \STR_PAD_LEFT) . '\')';
            };

            foreach ([...range(1, 0x1F), 0x7F] as $i) {
                $v = $makeReplaceFx($v, $i);
            }

            return $v;
        };

        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            if (!MysqlConnection::isServerMariaDb($this->getConnection()) && version_compare($this->getConnection()->getServerVersion(), '5.7.8') < 0) {
                self::assertSameSql('concat(\'[\', case when `u` + :a is not null then concat(\'"\', ' . $makeReplaceControlCharsFx('replace(replace(replace(`u` + :b, \'"\', \'\"\'), \'\\\', \'\\\\\'), \'\\\"\', \'\"\')') . ', \'"\') else \'null\' end, \']\')', $expr->render()[0]);
                self::assertSame([':a' => 10, ':b' => 10], $expr->render()[1]);
            } else {
                self::assertSameSql('json_array(`u` + :a)', $expr->render()[0]);
                self::assertSame([':a' => 10], $expr->render()[1]);
            }
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('json_build_array(`u` + :a)', $expr->render()[0]);
            self::assertSame([':a' => 10], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            if (version_compare($this->getConnection()->getServerVersion(), '16') < 0) {
                self::assertSameSql('concat(\'[\', case when `u` + :a is not null then concat(\'"\', ' . $makeReplaceControlCharsFx('replace(replace(replace(`u` + :b, \'"\', \'\"\'), \'\\\', \'\\\\\'), \'\\\"\', \'\"\')') . ', \'"\') else \'null\' end, \']\')', $expr->render()[0]);
                self::assertSame([':a' => 10, ':b' => 10], $expr->render()[1]);
            } else {
                self::assertSameSql('json_array(`u` + :a null on null)', $expr->render()[0]);
                self::assertSame([':a' => 10], $expr->render()[1]);
            }
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('json_array(`u` + :a null on null returning CLOB)', $expr->render()[0]);
            self::assertSame([':xxaaaa' => 10], $expr->render()[1]);
        } else {
            self::assertSameSql('json_array(`u` + :a)', $expr->render()[0]);
            self::assertSame([':a' => 10], $expr->render()[1]);
        }
    }

    /**
     * @dataProvider provideFxJsonArrayCases
     *
     * @param list<scalar|null> $values
     */
    #[DataProvider('provideFxJsonArrayCases')]
    public function testFxJsonArray(array $values): void
    {
        $res = $this->q()
            ->field($this->q()->fxJsonArray(
                array_map(fn ($v) => $this->e('[]' . ($v === null && $this->getDatabasePlatform() instanceof PostgreSQLPlatform ? '::text' : ''), [$v]), $values)
            ))
            ->getOne();

        self::assertStringStartsWith('[', $res);
        $resDecoded = json_decode($res);

        if ($resDecoded === null && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform && !MysqlConnection::isServerMariaDb($this->getConnection())
            && (version_compare($this->getConnection()->getServerVersion(), '5.7.8') >= 0 && version_compare($this->getConnection()->getServerVersion(), '5.7.20') <= 0)) {
            $resDecoded = $values;
        }

        self::{'assertEquals'}($values, $resDecoded);
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideFxJsonArrayCases(): iterable
    {
        yield [[]];
        yield [['foo']];

        foreach (self::provideFxJsonValueCases() as [$json, $path, $type]) {
            if ($path === '$' && $type === 'json') {
                $value = json_decode($json, true);

                if (is_scalar($value) || $value === null) {
                    yield [[$value, $value]];
                }
            }
        }
    }

    public function testFxJsonArrayJson(): void
    {
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && version_compare($this->getConnection()->getServerVersion(), MysqlConnection::isServerMariaDb($this->getConnection()) ? '10.6' : '8.0') < 0
            || $this->getDatabasePlatform() instanceof SQLServerPlatform && version_compare($this->getConnection()->getServerVersion(), '16') < 0
            || $this->getDatabasePlatform() instanceof OraclePlatform && version_compare($this->getConnection()->getServerVersion(), '21.0') < 0
        ) {
            self::markTestIncomplete('JSON type is not supported by some older databases');
        }

        $json = '{"v":10}';

        $res = $this->q()
            ->field($this->q()->fxJsonArray([
                $this->q()->fxJsonValue($this->e('[]', [$json]), '$', 'json'),
            ]))
            ->getOne();

        self::assertStringStartsWith('[', $res);
        self::assertSame(
            [['v' => 10]],
            json_decode($res, true)
        );
    }

    /**
     * @dataProvider provideFxJsonArrayCases
     *
     * @param list<scalar|null> $values
     */
    #[DataProvider('provideFxJsonArrayCases')]
    public function testFxJsonArrayAgg(array $values): void
    {
        // TODO set for every new MySQL/MariaDB connection by default
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $this->getConnection()->expr(
                'SET SESSION group_concat_max_len = ' . (4 * 1024 * 1024 * 1024 - 1)
            )->executeStatement();
        }

        if ($values === []) {
            $tableExpr = $this->q()->field($this->e('1'), 'v')->limit(0);
        } else {
            $tableExpr = $this->e(
                implode(' union all ', array_fill(0, count($values), '[]')),
                array_map(function ($v) {
                    $q = $this->q()->field($this->e('[]' . ($v === null && $this->getDatabasePlatform() instanceof PostgreSQLPlatform ? '::text' : ''), [$v]), 'v');
                    $q->wrapInParentheses = false;

                    return $q;
                }, $values)
            );
            $tableExpr->wrapInParentheses = true;
        }

        $res = $this->q()
            ->field($this->q()->fxJsonArrayAgg($this->e('{}', ['v'])))
            ->table($tableExpr, 't')
            ->getRows();

        self::assertCount(1, $res);
        self::assertCount(1, $res[0]);
        $res = array_first($res[0]);

        if ($values === []) {
            if ($res === '[]' && ($this->getDatabasePlatform() instanceof SQLitePlatform || $this->getDatabasePlatform() instanceof SQLServerPlatform)) {
                $res = null;
            }

            self::assertNull($res);
        } else {
            self::assertStringStartsWith('[', $res);
            $resDecoded = json_decode($res);

            // https://jira.mariadb.org/browse/MDEV-24784
            if ($resDecoded === null && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform && MysqlConnection::isServerMariaDb($this->getConnection()) && (
                (version_compare($this->getConnection()->getServerVersion(), '10.5') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.5.23') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.6') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.6.16') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.7') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.11.6') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '11.0') >= 0 && version_compare($this->getConnection()->getServerVersion(), '11.0.4') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '11.1') >= 0 && version_compare($this->getConnection()->getServerVersion(), '11.1.3') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '11.2') >= 0 && version_compare($this->getConnection()->getServerVersion(), '11.2.2') <= 0)
            )) {
                $resDecoded = $values;
            }

            self::{'assertEquals'}($values, $resDecoded);
        }
    }

    public function testFxJsonValueRenderInt(): void
    {
        $expr = $this->q()->fxJsonValue($this->e('[]', ['{"v":10}']), '$.v', 'bigint');

        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('case when json_type(:a, \'$.v\') not in(\'array\', \'object\') then json_extract(:b, \'$.v\') end', $expr->render()[0]);
            self::assertSame([':a' => '{"v":10}', ':b' => '{"v":10}'], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && (MysqlConnection::isServerMariaDb($this->getConnection()) || version_compare($this->getConnection()->getServerVersion(), '8.0') >= 0)) {
            if (MysqlConnection::isServerMariaDb($this->getConnection())) {
                self::assertSameSql('cast(json_value(:a, \'$.v\') as SIGNED)', $expr->render()[0]);
            } else {
                self::assertSameSql('json_value(cast(:a as JSON), \'$.v\' returning SIGNED)', $expr->render()[0]);
            }
            self::assertSame([':a' => '{"v":10}'], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if (version_compare($this->getConnection()->getServerVersion(), '17.0') < 0) {
                self::assertSameSql('select `c0` `cv` from xmltable(\'/t/r\' passing xmlparse(document :a) columns `c0` BIGINT path \'@c0\') `t`', $expr->render()[0]);
                self::assertSame([':a' => '<t><r c0="10"/></t>'], $expr->render()[1]);
            } else {
                self::assertSameSql('json_value(:a, \'strict $.v\' returning BIGINT)', $expr->render()[0]);
                self::assertSame([':a' => '{"v":10}'], $expr->render()[1]);
            }
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select `c0` `cv` from openjson(concat(\'[\', concat(\'[\', :a, \']\'), \']\'), \'$[0]\') with (`c0` BIGINT \'$.v\') `t`', $expr->render()[0]);
            self::assertSame([':a' => '{"v":10}'], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            if (version_compare($this->getConnection()->getServerVersion(), '21.0') < 0) {
                self::assertSameSql('json_value(concat(concat(TO_CLOB(\'[\'), TO_CLOB(:a)), TO_CLOB(\']\')), \'$[0].v\' returning NUMBER(20))', $expr->render()[0]);
            } else {
                self::assertSameSql('json_value(:a, \'$.v\' returning NUMBER(20))', $expr->render()[0]);
            }
            self::assertSame([':xxaaaa' => '{"v":10}'], $expr->render()[1]);
        } else {
            self::assertSameSql('select :a `cv`', $expr->render()[0]);
            self::assertSame([':a' => 10], $expr->render()[1]);
        }
    }

    public function testFxJsonValueRenderJson(): void
    {
        $expr = $this->q()->fxJsonValue($this->e('[]', ['{"v":10}']), '$.v', 'json');

        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('case json_type(:a, \'$.v\') when \'text\' then json_quote(json_extract(:b, \'$.v\')) when \'false\' then \'false\' when \'true\' then \'true\' else json_extract(:c, \'$.v\') end', $expr->render()[0]);
            self::assertSame([':a' => '{"v":10}', ':b' => '{"v":10}', ':c' => '{"v":10}'], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && (MysqlConnection::isServerMariaDb($this->getConnection()) || version_compare($this->getConnection()->getServerVersion(), '8.0') >= 0)) {
            if (MysqlConnection::isServerMariaDb($this->getConnection())) {
                self::assertSameSql('case when json_type(json_extract(:a, \'$.v\')) != \'NULL\' then json_extract(:b, \'$.v\') end', $expr->render()[0]);
            } else {
                self::assertSameSql('case when json_type(json_extract(cast(:a as JSON), \'$.v\')) != \'NULL\' then json_extract(cast(:b as JSON), \'$.v\') end', $expr->render()[0]);
            }
            self::assertSame([':a' => '{"v":10}', ':b' => '{"v":10}'], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            if (version_compare($this->getConnection()->getServerVersion(), '17.0') < 0) {
                self::assertSameSql('select `c0` `cv` from xmltable(\'/t/r\' passing xmlparse(document :a) columns `c0` JSON path \'@c0\') `t`', $expr->render()[0]);
                self::assertSame([':a' => '<t><r c0="10"/></t>'], $expr->render()[1]);
            } else {
                self::assertSameSql('case when json_typeof(json_query(:a, \'strict $.v\' returning JSON)) != \'null\' then json_query(:b, \'strict $.v\' returning JSON) end', $expr->render()[0]);
                self::assertSame([':a' => '{"v":10}', ':b' => '{"v":10}'], $expr->render()[1]);
            }
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select `c0` `cv` from openjson(concat(\'[\', concat(\'[\', :a, \']\'), \']\'), \'$[0]\') with (`c0` NVARCHAR(MAX) \'$.v\' as json) `t`', $expr->render()[0]);
            self::assertSame([':a' => '{"v":10}'], $expr->render()[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            if (version_compare($this->getConnection()->getServerVersion(), '21.0') < 0) {
                self::assertSameSql('case when not json_equal(json_query(concat(concat(TO_CLOB(\'[\'), TO_CLOB(:a)), TO_CLOB(\']\')), \'$[0].v\' returning CLOB), \'null\') then json_query(concat(concat(TO_CLOB(\'[\'), TO_CLOB(:b)), TO_CLOB(\']\')), \'$[0].v\' returning CLOB) end', $expr->render()[0]);
            } else {
                self::assertSameSql('case when not json_equal(json_query(:a, \'$.v\' returning CLOB), \'null\') then json_query(:b, \'$.v\' returning CLOB) end', $expr->render()[0]);
            }
            self::assertSame([':xxaaaa' => '{"v":10}', ':xxaaab' => '{"v":10}'], $expr->render()[1]);
        } else {
            self::assertSameSql('select :a `cv`', $expr->render()[0]);
            self::assertSame([':a' => '10'], $expr->render()[1]);
        }
    }

    private function fixExpectedJsonUsingPlatform(string $json, bool $forJsonValue): string
    {
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && (
            MysqlConnection::isServerMariaDb($this->getConnection())
                ? $forJsonValue
                : version_compare($this->getConnection()->getServerVersion(), '8.0') >= 0
        )
        || ($this->getDatabasePlatform() instanceof PostgreSQLPlatform && version_compare($this->getConnection()->getServerVersion(), '17.0') >= 0)
        ) {
            $json = str_replace('":', '": ', $json);
        }

        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $json = preg_replace_callback('~(?<=\\\u)00[01][a-f]~', static fn ($matches) => strtoupper($matches[0]), $json);
            $json = str_replace(chr(0x7F), '\u007F', $json);
        }

        return $json;
    }

    /**
     * @dataProvider provideFxJsonValueCases
     *
     * @param 'boolean'|'bigint'|'float'|'string'|'json' $type
     * @param string|null                                $expectedValue
     */
    #[DataProvider('provideFxJsonValueCases')]
    public function testFxJsonValue(string $json, string $path, string $type, $expectedValue): void
    {
        if ($json === '10' && $path === '$[0]' && $expectedValue === null && (
            ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && (
                MysqlConnection::isServerMariaDb($this->getConnection())
                || version_compare($this->getConnection()->getServerVersion(), '8.0') >= 0
            ))
            || $this->getDatabasePlatform() instanceof OraclePlatform
        )) {
            $expectedValue = '10';
        }

        // https://jira.mariadb.org/browse/MDEV-37428
        if ($json === '""' && $path === '$' && $expectedValue === '' && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform
            && MysqlConnection::isServerMariaDb($this->getConnection())
            && in_array($this->getConnection()->getServerVersion(), ['10.11.14', '11.4.8', '11.8.3', '12.0.2'], true)) {
            $expectedValue = null;
        }

        // https://jira.mariadb.org/browse/MDEV-27151
        if (($json === 'null' && $path === '$' || $json === '[null]' && $path === '$[0]') && $type !== 'json' && $expectedValue === null
            && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform && MysqlConnection::isServerMariaDb($this->getConnection()) && (
                version_compare($this->getConnection()->getServerVersion(), '10.3.36') <= 0
                || (version_compare($this->getConnection()->getServerVersion(), '10.4') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.4.26') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.5') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.5.17') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.6') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.6.9') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.7') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.7.5') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.8') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.8.4') <= 0)
                || (version_compare($this->getConnection()->getServerVersion(), '10.9') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.9.2') <= 0)
            )) {
            $expectedValue = $type === 'string'
                ? 'null'
                : ($type === 'float' ? '0.0' : '0');
        }

        // https://jira.mariadb.org/browse/MDEV-15905
        if ($json === 'true' && $path === '$' && $expectedValue === '1' && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform
            && MysqlConnection::isServerMariaDb($this->getConnection()) && (
                version_compare($this->getConnection()->getServerVersion(), '10.2.15') <= 0
                || (version_compare($this->getConnection()->getServerVersion(), '10.3') >= 0 && version_compare($this->getConnection()->getServerVersion(), '10.3.7') <= 0)
            )) {
            $expectedValue = '0';
        }

        if ($type === 'json' && $expectedValue !== null) {
            $expectedValue = $this->fixExpectedJsonUsingPlatform($expectedValue, true);
        }

        if ($type === 'json' && is_scalar(json_decode($expectedValue ?? '[]', true)) && (
            $this->getDatabasePlatform() instanceof SQLServerPlatform // TODO https://stackoverflow.com/questions/73282836/how-to-query-a-key-in-a-sql-server-json-column-if-it-could-be-a-scalar-value-or
            || ($this->getDatabasePlatform() instanceof OraclePlatform && version_compare($this->getConnection()->getServerVersion(), '21.0') < 0)
        )) {
            $expectedValue = null;
        }

        // TODO Oracle always converts empty string to null
        // https://stackoverflow.com/questions/13278773/null-vs-empty-string-in-oracle#13278879
        if ($expectedValue === '' && $this->getDatabasePlatform() instanceof OraclePlatform) {
            $expectedValue = null;
        }

        self::assertSame(
            $expectedValue,
            $this->q()
                ->field($this->q()->fxJsonValue($this->e('[]', [$json]), $path, $type))
                ->getOne()
        );
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideFxJsonValueCases(): iterable
    {
        foreach (['boolean', 'bigint', 'float', 'string', 'json'] as $type) {
            yield ['null', '$', $type, null];
            yield ['[null]', '$[0]', $type, null];
        }

        yield ['10', '$', 'bigint', '10'];
        yield [(string) \PHP_INT_MAX, '$', 'bigint', (string) \PHP_INT_MAX];
        yield [(string) \PHP_INT_MIN, '$', 'bigint', (string) \PHP_INT_MIN];
        yield ['{"v":10}', '$.v', 'bigint', '10'];
        yield ['[{"v":1},{"v":10}]', '$[1].v', 'bigint', '10'];
        yield ['{"v.[* ":10}', '$."v.[* "', 'bigint', '10'];

        yield ['[]', '$.v', 'bigint', null];
        yield ['{}', '$.v', 'bigint', null];
        yield ['{"v":[10]}', '$.v', 'bigint', null];
        yield ['{"v":{"w":20}}', '$.v', 'bigint', null];
        yield ['10', '$.v', 'bigint', null];
        yield ['10', '$[0]', 'bigint', null];

        yield ['false', '$', 'boolean', '0'];
        yield ['true', '$', 'boolean', '1'];
        yield ['"null"', '$', 'string', 'null'];
        yield ['"nuLL"', '$', 'string', 'nuLL'];
        yield ['"false"', '$', 'string', 'false'];
        yield ['""', '$', 'string', ''];

        $strAllChars = '';
        for ($i = 1; $i < (str_starts_with($_ENV['DB_DSN'], 'pdo_oci') ? 150 : 600); ++$i) {
            $strAllChars .= mb_chr($i);
        }

        foreach ([
            '[]',
            '[[[[1]]]]',
            // '{}',
            '{"010":"020"}',
            '{"k":{"k":{"k":{"k":1}}}}',
            '10',
            (string) \PHP_INT_MAX,
            (string) \PHP_INT_MIN,
            '10.0',
            '"10"',
            '"10.0"',
            '"10.00"',
            json_encode($strAllChars, \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
            'false',
            'true',
            '[null]',
        ] as $json) {
            yield [$json, '$', 'json', $json];
            yield ['[' . $json . ']', '$[0]', 'json', $json];
            yield ['[' . $json . ']', '$', 'json', '[' . $json . ']'];
        }

        // TODO report to PHP/Oracle
        $isOracle = str_starts_with($_ENV['DB_DSN'], 'oci8') || str_starts_with($_ENV['DB_DSN'], 'pdo_oci');

        foreach ([
            '"' . str_repeat('x', $isOracle ? 160 : 80_000) . '"',
            '"' . str_repeat('🔥', $isOracle ? 40 : 20_000) . '"',
        ] as $json) {
            yield [$json, '$', 'json', $json];
        }
    }

    /**
     * @dataProvider provideJsonTableCases
     *
     * @param non-empty-array<string, array{path: string, type: 'boolean'|'bigint'|'float'|'string'|'json'}> $columns
     * @param list<array<string, string|null>>                                                               $expectedRows
     */
    #[DataProvider('provideJsonTableCases')]
    public function testJsonTable(string $json, ?string $rowsPath, array $columns, array $expectedRows): void
    {
        if ($json === '[[10],20]' && $expectedRows === [['foo' => '10'], ['foo' => null]] && (
            ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && (
                MysqlConnection::isServerMariaDb($this->getConnection())
                    ? version_compare($this->getConnection()->getServerVersion(), '10.6') >= 0
                    : version_compare($this->getConnection()->getServerVersion(), '8.0') >= 0
            ))
            || $this->getDatabasePlatform() instanceof OraclePlatform
        )) {
            $expectedRows = [['foo' => '10'], ['foo' => '20']];
        }

        foreach ($columns as $k => $column) {
            if ($column['type'] === 'json') {
                $expectedRows = array_map(fn ($row) => array_map(fn ($v) => $v !== null ? $this->fixExpectedJsonUsingPlatform($v, false) : null, $row), $expectedRows);
            }
        }

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform // TODO
            || ($this->getDatabasePlatform() instanceof OraclePlatform && version_compare($this->getConnection()->getServerVersion(), '21.0') < 0)
        ) {
            foreach ($columns as $k => $column) {
                if ($column['type'] === 'json') {
                    $expectedRows = array_map(static fn ($row) => array_map(static fn ($v) => is_scalar(json_decode($v ?? '[]', true)) ? null : $v, $row), $expectedRows);
                }
            }
        }

        self::assertSame(
            $expectedRows,
            $this->q()
                ->jsonTable($this->e('[]', [$json]), $columns, $rowsPath ?? '$[*]')
                ->getRows()
        );
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideJsonTableCases(): iterable
    {
        yield ['[]', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], []];
        yield ['[]', null, ['foo' => ['path' => '$', 'type' => 'bigint']], []];

        yield ['[10,null]', null, ['foo' => ['path' => '$', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => null],
        ]];

        yield ['[{"v":10},{"v":20}]', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => '20'],
        ]];

        yield ['{"x":[{"v":10},{"v":20}]}', '$.x[*]', ['foo' => ['path' => '$.v', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => '20'],
        ]];

        yield ['[[{"v":1},{"v":10}],[[],{"v":20}]]', null, ['foo' => ['path' => '$[1].v', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => '20'],
        ]];

        yield ['[{"v.[* ":10},{"v.[* ":20}]', null, ['foo' => ['path' => '$."v.[* "', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => '20'],
        ]];

        yield ['[{"v":10},{}]', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => null],
        ]];

        yield ['[{"v":[10]},{}]', null, ['foo' => ['path' => '$.v[0]', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => null],
        ]];

        yield ['[{},{}]', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], [
            ['foo' => null],
            ['foo' => null],
        ]];

        yield ['[{"v":[10]},{"v":{"w":20}}]', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], [
            ['foo' => null],
            ['foo' => null],
        ]];

        yield ['[{"v":10},20]', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => null],
        ]];

        yield ['null', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], []];
        yield ['1', null, ['foo' => ['path' => '$.v', 'type' => 'bigint']], []];

        yield ['null', null, ['foo' => ['path' => '$', 'type' => 'bigint']], []];
        yield ['1', null, ['foo' => ['path' => '$', 'type' => 'bigint']], []];

        yield ['[[10],20]', null, ['foo' => ['path' => '$[0]', 'type' => 'bigint']], [
            ['foo' => '10'],
            ['foo' => null],
        ]];

        $jsons = [];
        foreach (self::provideFxJsonValueCases() as [$json, $path, $type]) {
            if ($type === 'json' && $path === '$') {
                $jsons[] = $json;
            }
        }
        yield [
            '[' . implode(',', $jsons) . ']',
            null,
            ['foo' => ['path' => '$', 'type' => 'json']],
            array_map(static fn ($v) => ['foo' => $v !== 'null' ? $v : null], $jsons),
        ];
        yield [
            '[' . implode(',', array_map(static fn ($v) => '[' . $v . ']', $jsons)) . ']',
            null,
            ['foo' => ['path' => '$[0]', 'type' => 'json']],
            array_map(static fn ($v) => ['foo' => $v !== 'null' ? $v : null], $jsons),
        ];
        yield [
            '[' . implode(',', array_map(static fn ($v) => '[' . $v . ']', $jsons)) . ']',
            null,
            ['foo' => ['path' => '$', 'type' => 'json']],
            array_map(static fn ($v) => ['foo' => '[' . $v . ']'], $jsons),
        ];
    }

    public function testJsonTableHuge(): void
    {
        $columns = [];
        for ($i = 0; $i < 5; ++$i) {
            $columns['i' . $i] = ['path' => '$.v' . $i . '[0]', 'type' => 'bigint'];
            $columns['s' . $i] = ['path' => '$.v' . $i . '[1]', 'type' => 'string'];
        }

        $jsonRows = [];
        $expectedRows = [];
        for ($i = 0; $i < 1_050; ++$i) {
            $jsonRow = [];
            $expectedRow = [];
            for ($j = 0; $j < count($columns) / 2; ++$j) {
                $jsonRow['v' . $j] = [$i * $i, $i . '_' . $j];
                $expectedRow['i' . $j] = (string) array_last($jsonRow)[0];
                $expectedRow['s' . $j] = array_last($jsonRow)[1];
            }

            $jsonRows[] = $jsonRow;
            $expectedRows[] = $expectedRow;
        }

        self::assertSame(
            $expectedRows,
            $this->q()
                ->jsonTable($this->e('[]', [json_encode($jsonRows)]), $columns)
                ->getRows()
        );
    }

    public function testInsertFromArrayTable(): void
    {
        $this->setupTables();

        $this->q('employee')->mode('truncate')->executeStatement();

        $this->q('employee')
            ->setSelect(
                \Closure::bind(static fn ($q) => $q->makeArrayTable([
                    ['id' => 1, 'name' => 'John', 'retired' => true],
                    ['id' => 2, 'name' => 'Jane', 'retired' => false],
                ], ['id' => 'integer', 'name' => 'string', 'retired' => 'boolean']), null, Expression::class)($this->q()),
                ['id', 'name', 'retired']
            )
            ->mode('insert')->executeStatement();

        self::assertSame([
            ['id' => '1', 'name' => 'John', 'retired' => '1'],
            ['id' => '2', 'name' => 'Jane', 'retired' => '0'],
        ], $this->q('employee')->field('id')->field('name')->field('retired')->order('id')->getRows());
    }

    public function testGetRowEmpty(): void
    {
        $this->setupTables();

        $this->q('employee')->mode('truncate')->executeStatement();

        self::assertNull($this->q('employee')->getRow());
    }

    public function testGetOneEmptyException(): void
    {
        $this->setupTables();

        $this->q('employee')->mode('truncate')->executeStatement();

        $q = $this->q('employee')->field('name');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to fetch single cell of data');
        $q->getOne();
    }

    public function testSelectUnexistingColumnException(): void
    {
        $this->setupTables();

        $q = $this->q('employee')->field('Sqlite must use backticks for identifier escape');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('An exception occurred while executing a query: ');
        $q->executeStatement();
    }

    public function testSelectExtremeNumbers(): void
    {
        $values = [
            0,
            \PHP_INT_MIN,
            \PHP_INT_MAX,
            0.0,
            -1.5,
            1e25,
            1e50,
            1e-25,
            1e-50,
            // https://github.com/atk4/data/blob/6.0.0/tests/TypecastingTest.php#L128
            $this->getDatabasePlatform() instanceof SQLitePlatform
                ? 1.79769313486231e308
                : 1.7976931348623157e308,
            $this->getDatabasePlatform() instanceof SQLServerPlatform
                ? 2.2250738585072014e-308
                : 5e-324,
        ];

        $query = $this->q();
        foreach ($values as $k => $v) {
            $query->field($this->e('[]', [$v]), (string) $k);
        }

        $res = $query->getRow();

        // fix CI with old SQLite
        // fixed probably by "long double" hardware support - https://www.sqlite.org/releaselog/3_44_0.html
        if ($this->getDatabasePlatform() instanceof SQLitePlatform && version_compare(SqliteConnection::getDriverVersion(), '3.44') < 0) {
            if ($res[7] >= 0.999999999999999e-25 && $res[7] <= 1.00000000000001e-25) { // @phpstan-ignore offsetAccess.notFound
                $res[7] = 1e-25;
            }
            if ($res[8] >= 0.999999999999999e-50 && $res[8] <= 1.00000000000001e-50) {
                $res[8] = 1e-50;
            }
            if ($res[9] >= 1.79769313486231e308 && $res[9] <= 1.79769313486232e308) {
                $res[9] = 1.79769313486231e308;
            }
        }

        self::{'assertEquals'}($values, $res);
    }

    public function testConnectionGetServerVersion(): void
    {
        self::assertTrue(version_compare($this->getConnection()->getServerVersion(), '2.0') > 0);
        self::assertTrue(version_compare($this->getConnection()->getServerVersion(), '1000.0') < 0);
    }

    public function testMysqlConnectionIsServerMariaDb(): void
    {
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            if (MysqlConnection::isServerMariaDb($this->getConnection())) {
                if (Connection::isDbal3x()) {
                    self::assertInstanceOf(Platforms\MySQLPlatform::class, $this->getDatabasePlatform());
                } else {
                    self::assertNotInstanceOf(Platforms\MySQLPlatform::class, $this->getDatabasePlatform());
                }
                self::assertInstanceOf(Platforms\MariaDBPlatform::class, $this->getDatabasePlatform());
            } else {
                self::assertInstanceOf(Platforms\MySQLPlatform::class, $this->getDatabasePlatform());
                self::assertNotInstanceOf(Platforms\MariaDBPlatform::class, $this->getDatabasePlatform());
            }
        } else {
            self::assertTrue(true); // @phpstan-ignore staticMethod.alreadyNarrowedType
        }
    }

    public function testWhereExpression(): void
    {
        $this->setupTables();

        self::assertSame([
            ['id' => '2', 'name' => 'Jack', 'surname' => 'Williams', 'retired' => '1'],
        ], $this->q('employee')->where('retired', true)->where($this->q()->expr('{}=[] or {}=[]', ['surname', 'Williams', 'surname', 'Smith']))->getRows());
    }

    /**
     * @dataProvider provideWhereNumericCompareCases
     *
     * @param array{string, 1?: array<mixed>} $exprLeft
     * @param array{string, 1?: array<mixed>} $exprRight
     */
    #[DataProvider('provideWhereNumericCompareCases')]
    public function testWhereNumericCompare(array $exprLeft, string $operator, array $exprRight, bool $expectPostgresqlTypeMismatchException = false, bool $expectMssqlTypeMismatchException = false): void
    {
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $exprLeft[0] = preg_replace('~\d+[eE][\-+]?\d+~', '$0d', $exprLeft[0]);
        }

        $queryWhere = $this->q()->field($this->e('1'), 'v');
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $queryWhere->table('(select 1)', 'dual'); // needed for MySQL 5.x when WHERE or HAVING is specified
        }
        $queryWhere->where($this->e(...$exprLeft), $operator, $this->e(...$exprRight));

        $queryHaving = $this->q()->field($this->e('1'), 'v');
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $queryHaving->table('(select 1)', 'dual'); // needed for MySQL 5.x when WHERE or HAVING is specified
        }
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            $queryHaving->group('v');
        }
        $queryHaving->having($this->e(...$exprLeft), $operator, $this->e(...$exprRight));

        $queryWhereSub = $this->q()->field($this->e('1'), 'v');
        $queryWhereSub->table($this->q()->field($this->e(...$exprLeft), 'a')->field($this->e(...$exprRight), 'b'), 't');
        $queryWhereSub->where('a', $operator, $this->e('{}', ['b']));

        $queryWhereIn = $this->q()->field($this->e('1'), 'v');
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            $queryWhereIn->table('(select 1)', 'dual'); // needed for MySQL 5.x when WHERE or HAVING is specified
        }
        if ($operator === '=' || $operator === '!=') {
            $queryWhereIn->where(
                $this->e(...$exprLeft),
                $operator === '!=' ? 'not in' : 'in',
                [$this->e(...$exprRight), $this->e(...$exprRight)]
            );
        }

        $queryAll = $this->q()
            ->field($queryWhere, 'where')
            ->field($queryHaving, 'having')
            ->field($queryWhereSub, 'where_sub')
            ->field($queryWhereIn, 'where_in');

        if (($expectPostgresqlTypeMismatchException && $this->getDatabasePlatform() instanceof PostgreSQLPlatform) || ($expectMssqlTypeMismatchException && $this->getDatabasePlatform() instanceof SQLServerPlatform)) {
            $this->expectException(ExecuteException::class);
        }
        try {
            $rows = $queryAll->getRows();
        } catch (ExecuteException $e) {
            if ($expectPostgresqlTypeMismatchException && $this->getDatabasePlatform() instanceof PostgreSQLPlatform && str_contains($e->getPrevious()->getMessage(), 'operator does not exist')) {
                // https://dbfiddle.uk/YJvvOTpR
                self::markTestIncomplete('PostgreSQL does not implicitly cast string for numeric comparison');
            } elseif ($expectMssqlTypeMismatchException && $this->getDatabasePlatform() instanceof SQLServerPlatform && str_contains($e->getPrevious()->getMessage(), 'Conversion failed when converting the nvarchar value \'4.0\' to data type int')) {
                // https://dbfiddle.uk/YmYeklp_
                self::markTestIncomplete('MSSQL does not implicitly cast string with decimal point for float comparison');
            }

            throw $e;
        }

        self::assertSame(
            [['where' => '1', 'having' => '1', 'where_sub' => '1', 'where_in' => '1']],
            $rows
        );
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideWhereNumericCompareCases(): iterable
    {
        yield [['4'], '=', ['4']];
        yield [['0'], '=', ['0']];
        yield [['4'], '<', ['5']];
        yield [['5'], '>', ['4']];
        yield [['\'4\''], '=', ['\'4\'']];
        yield [['\'04\''], '=', ['\'04\'']];
        yield [['\'4\''], '!=', ['\'04\'']];
        yield [['\'4\''], '!=', ['\'4.0\'']];
        yield [['\'2e4\''], '<', ['\'3e3\'']];
        yield [['\'2e4\''], '>', ['\'1e5\'']];
        yield [['4.4'], '=', ['4.4']];
        yield [['0.0'], '=', ['0.0']];
        yield [['4.4'], '!=', ['4.3']];

        yield [['4'], '=', ['[]', [4]]];
        yield [['0'], '=', ['[]', [0]]];
        yield [['\'4\''], '=', ['[]', ['4']]];
        yield [['\'04\''], '=', ['[]', ['04']]];
        yield [['\'4\''], '!=', ['[]', ['04']]];
        yield [['\'4\''], '!=', ['[]', ['4.0']]];
        yield [['\'2e4\''], '<', ['[]', ['3e3']]];
        yield [['\'2e4\''], '>', ['[]', ['1e5']]];
        yield [['4.4'], '=', ['[]', [4.4]]];
        yield [['0.0'], '=', ['[]', [0.0]]];
        yield [['4.4'], '!=', ['[]', [4.3]]];
        yield [['4e1'], '=', ['[]', [40.0]]];
        yield [[(string) \PHP_INT_MAX], '=', ['[]', [\PHP_INT_MAX]]];
        yield [[(string) \PHP_INT_MIN], '=', ['[]', [\PHP_INT_MIN]]];
        yield [[(string) (\PHP_INT_MAX - 1)], '<', ['[]', [\PHP_INT_MAX]]];
        yield [[(string) \PHP_INT_MAX], '>', ['[]', [\PHP_INT_MAX - 1]]];
        yield [[Expression::castFloatToString(\PHP_FLOAT_MAX)], '=', ['[]', [\PHP_FLOAT_MAX]]];
        yield [[Expression::castFloatToString(\PHP_FLOAT_MIN)], '=', ['[]', [\PHP_FLOAT_MIN]]];
        yield [['0.0'], '<', ['[]', [\PHP_FLOAT_MIN]]];
        yield [['1.0'], '<', ['[]', [1.0 + \PHP_FLOAT_EPSILON]]];
        yield [['2e305'], '<', ['[]', [1e306]]];
        yield [['2e305'], '>', ['[]', [3e304]]];

        yield [['[]', [4]], '=', ['[]', [4]]];
        yield [['[]', ['4']], '=', ['[]', ['4']]];
        yield [['[]', ['2e4']], '<', ['[]', ['3e3']]];
        yield [['[]', ['2e4']], '>', ['[]', ['1e5']]];
        yield [['[]', [4.4]], '=', ['[]', [4.4]]];
        yield [['[]', [4.4]], '>', ['[]', [4.3]]];
        yield [['[]', [2e305]], '<', ['[]', [1e306]]];
        yield [['[]', [2e305]], '>', ['[]', [3e304]]];
        yield [['[]', [false]], '=', ['[]', [false]]];
        yield [['[]', [true]], '=', ['[]', [true]]];
        yield [['[]', [false]], '!=', ['[]', [true]]];
        yield [['[]', [false]], '<', ['[]', [true]]];

        yield [['4'], '=', ['[]', ['04']], true];
        yield [['\'04\''], '=', ['[]', [4]], true];
        yield [['4'], '=', ['[]', [4.0]]];
        yield [['4'], '=', ['[]', ['4.0']], true, true];
        yield [['2.5'], '=', ['[]', ['02.50']], true];
        yield [['0'], '=', ['[]', [false]], true];
        yield [['0'], '!=', ['[]', [true]], true];
        yield [['1'], '=', ['[]', [true]], true];
        yield [['1'], '!=', ['[]', [false]], true];

        yield [['2 + 2'], '=', ['[]', [4]]];
        yield [['2 + 2'], '=', ['[] + []', [1, 3]]];
        yield [['[] + []', [-1, 5]], '=', ['[] + []', [1, 3]]];
        yield [['2 + 2'], '=', ['[]', ['4']], true];
        yield [['2 + 2.5'], '=', ['[]', [4.5]]];
        yield [['2 + 2.5'], '=', ['[] + []', [1.5, 3.0]]];
        yield [['[] + []', [-1.5, 6.0]], '=', ['[] + []', [1.5, 3.0]]];
        yield [['2 + 2.5'], '=', ['[]', ['4.5']], true];
    }

    public function testGroupConcat(): void
    {
        $q = $this->q()
            ->table('people')
            ->group('age')
            ->field('age')
            ->field($this->q()->groupConcat('name', ','));

        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
            self::assertSame([
                'select `age`, group_concat(`name` separator \',\') from `people` group by `age`',
                [],
            ], $q->render());
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSame([
                'select "age", string_agg("name", :a) from "people" group by "age"',
                [':a' => ','],
            ], $q->render());
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSame([
                'select [age], string_agg([name], N\',\') from [people] group by [age]',
                [],
            ], $q->render());
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame([
                'select "age", listagg("name", :xxaaaa) within group (order by "name") from "people" group by "age"',
                [':xxaaaa' => ','],
            ], $q->render());
        } else {
            self::assertSame([
                'select `age`, group_concat(`name`, :a) from `people` group by `age`',
                [':a' => ','],
            ], $q->render());
        }
    }

    public function testExists(): void
    {
        $q = $this->q()
            ->table('contacts')
            ->where('first_name', 'John')
            ->exists();

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSame([
                'select case when exists(select * from [contacts] where [first_name] = :a) then 1 else 0 end',
                [':a' => 'John'],
            ], $q->render());
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSame([
                'select case when exists(select * from "contacts" where "first_name" = :xxaaaa) then 1 else 0 end from "DUAL"',
                [':xxaaaa' => 'John'],
            ], $q->render());
        } else {
            self::assertSameSql('select exists (select * from `contacts` where `first_name` = :a)', $q->render()[0]);
            self::assertSame([':a' => 'John'], $q->render()[1]);
        }
    }

    public function testExecuteException(): void
    {
        $q = $this->q('non_existing_table')->field('non_existing_field');

        $this->expectException(ExecuteException::class);
        $this->expectExceptionMessage('An exception occurred while executing a query: ');
        try {
            $q->getOne();
        } catch (ExecuteException $e) {
            if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                $expectedErrorCode = 1146; // SQLSTATE[42S02]: Base table or view not found: 1146 Table 'non_existing_table' doesn't exist
            } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $expectedErrorCode = 7; // SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "non_existing_table" does not exist
            } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
                $expectedErrorCode = 208; // SQLSTATE[42S02]: Invalid object name 'non_existing_table'
            } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
                $expectedErrorCode = 942; // SQLSTATE[HY000]: ORA-00942: table or view does not exist
            } else {
                $expectedErrorCode = 1; // SQLSTATE[HY000]: General error: 1 no such table: non_existing_table
            }

            self::assertSame($expectedErrorCode, $e->getCode());
            $this->assertSameSql(
                preg_replace('~\s+~', '', 'select `non_existing_field` from `non_existing_table`'),
                preg_replace('~\s+~', '', $e->getDebugQuery())
            );

            throw $e;
        }
    }

    public function testQuotedTokenRegexConstant(): void
    {
        $hasCommentCarriageReturnSupport = $this->getDatabasePlatform() instanceof PostgreSQLPlatform
            || $this->getDatabasePlatform() instanceof SQLServerPlatform;
        $hasBackslashSupport = $this->getDatabasePlatform() instanceof AbstractMySQLPlatform;

        self::assertSame(
            '(?:(?sx)' . "\n"
                . '    \'(?:[^\'' . ($hasBackslashSupport ? '\\\\' : '') . ']+' . ($hasBackslashSupport ? '|\\\.' : '') . '|\'\')*+\'' . "\n"
                . '    |"(?:[^"' . ($hasBackslashSupport ? '\\\\' : '') . ']+' . ($hasBackslashSupport ? '|\\\.' : '') . '|"")*+"' . "\n"
                . '    |`(?:[^`]+|``)*+`' . "\n"
                . '    |\[(?:[^\]]+|\]\])*+\]' . "\n"
                . '    |(?:--' . (
                    $this->getDatabasePlatform() instanceof AbstractMySQLPlatform
                        ? '(?=$|[\x01-\x21\x7f])'
                        : ''
                ) . '|\#)[^' . ($hasCommentCarriageReturnSupport ? '\r' : '') . '\n]*+' . "\n"
                . '    |/\*(?:[^*]+|\*(?!/))*+\*/' . "\n"
                . ')',
            $this->e()::QUOTED_TOKEN_REGEX
        );

        self::assertSame($this->e()::QUOTED_TOKEN_REGEX, $this->q()::QUOTED_TOKEN_REGEX);

        $sqlTwoEscape = '\'\'\'\'';
        $sqlBackslashEscape = '\'\\\'-- \'';
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $sqlBackslashEscape .= "\n/**/";
        }

        $query = $this->q()->field($this->e($sqlTwoEscape));
        self::assertSame('\'', $query->getOne());

        $query = $this->q()->field($this->e($sqlBackslashEscape));
        self::assertSame($hasBackslashSupport ? '\'-- ' : '\\', $query->getOne());

        foreach (['"', '`'] as $chr) {
            if ($chr === '`' && ($this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof SQLServerPlatform || $this->getDatabasePlatform() instanceof OraclePlatform)) {
                continue;
            }

            $replaceFx = static fn ($v) => str_replace('\'', $chr, $v);
            $needsExplicitAs = $chr === '"' && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform;

            if ($chr !== '"' || !$this->getDatabasePlatform() instanceof OraclePlatform) {
                $query = $this->q()->field($this->e('\'x\' ' . ($needsExplicitAs ? 'as ' : '') . $replaceFx($sqlTwoEscape)));
                self::assertSame([$chr => 'x'], $query->getRow());
            }

            $query = $this->q()->field($this->e('\'x\' ' . ($needsExplicitAs ? 'as ' : '') . $replaceFx($sqlBackslashEscape)));
            self::assertSame([$hasBackslashSupport && $chr === '"' ? $chr . '-- ' : '\\' => 'x'], $query->getRow());
        }

        if (!($this->getDatabasePlatform() instanceof AbstractMySQLPlatform || $this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof OraclePlatform)) {
            $query = $this->q()->field($this->e('\'x\' [a*b]'));
            self::assertSame(['a*b' => 'x'], $query->getRow());

            $replaceFx = static fn ($v) => str_replace('\'', ']', preg_replace('~^\'~', '[a*b', $v));

            if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
                $query = $this->q()->field($this->e('\'x\' ' . $replaceFx($sqlTwoEscape)));
                self::assertSame(['a*b]' => 'x'], $query->getRow());
            }

            $query = $this->q()->field($this->e('\'x\' ' . $replaceFx($sqlBackslashEscape)));
            self::assertSame(['a*b\\' => 'x'], $query->getRow());
        }
    }

    public function testEscapeStringLiteral(): void
    {
        $chars = [];
        for ($i = 0; $i <= 0xFF; ++$i) {
            $chr = chr($i);
            $chars[] = $chr;

            if ($chr === '1') {
                $i += 7;
            } elseif ($chr === 'B' || $chr === 'b') {
                $i += 23;
            }
        }

        $str = '';
        foreach ($chars as $chr1) {
            foreach ($chars as $chr2) {
                foreach (['\\', '\''] as $chr3) {
                    $str .= $chr1 . $chr2 . $chr3;
                }
            }
        }
        foreach ($chars as $chr) {
            for ($i = 1; $i <= 3; ++$i) {
                $str .= str_repeat($chr, $i) . '?';
                for ($j = 1; $j <= 3; ++$j) {
                    $str .= str_repeat('\\', $j) . str_repeat($chr, $i) . ':n';
                }
            }
        }
        for ($i = 1; $i <= 10_000; $i = (int) ceil($i * 1.1)) {
            $str .= str_repeat('\\', $i) . str_repeat("\0", $i);
        }

        // TODO full binary support
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform
            || $this->getDatabasePlatform() instanceof SQLServerPlatform
            || $this->getDatabasePlatform() instanceof OraclePlatform
        ) {
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        }

        // https://github.com/php/php-src/issues/8928 and https://github.com/php/php-src/issues/18873
        if (\PHP_VERSION_ID < 8_02_00 && str_starts_with($_ENV['DB_DSN'], 'oci8')) {
            $str = substr($str, 0, 1000);
        }

        // PostgreSQL does not support \0 character
        // https://stackoverflow.com/questions/1347646/postgres-error-on-insert-error-invalid-byte-sequence-for-encoding-utf8-0x0
        $str2 = $this->getDatabasePlatform() instanceof PostgreSQLPlatform
            ? str_replace("\0", '-', $str)
            : $str;

        $dummyExpression = $this->e();
        $strSql = \Closure::bind(static fn () => $dummyExpression->escapeStringLiteral($str2), null, Expression::class)();
        $query = $this->q()->field($this->e($strSql));
        self::assertSame(bin2hex($str2), bin2hex($query->getOne()));

        if ($str2 !== $str) {
            $strSql = \Closure::bind(static fn () => $dummyExpression->escapeStringLiteral($str), null, Expression::class)();
            $query = $this->q()->field($this->e($strSql));

            $this->expectException(ExecuteException::class);
            $this->expectExceptionMessage('Character not in repertoire');
            $query->getOne();
        }
    }

    public function testEscapeIdentifier(): void
    {
        $expected = [];
        $query = $this->q();
        foreach ([
            'foo',
            'a b',
            'a  b',
            "a\nb",
            "a\tb",
            '2',
            '\'',
            '"',
            '`',
            '[',
            ']',
            '\\',
            '\\\\',
            '\\\\\\',
            '\\\\\\\\',
            '\n',
            '.',
            '*',
            '?',
            ':',
            ':x',
            ':1',
            ';',
            '--',
            '#',
        ] as $k => $v) {
            if ($v === '"' && $this->getDatabasePlatform() instanceof OraclePlatform) { // Oracle identifier cannot contain double quote
                continue;
            } elseif (($v === '\\' || $v === '\\\\\\') && ($this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof OraclePlatform)) { // https://github.com/php/php-src/issues/13958
                continue;
            } elseif (($v === '?' || $v === ':x' || $v === ':1' || $v === '--') && $this->getDatabasePlatform() instanceof AbstractMySQLPlatform) { // TODO pdo_mysql only https://dbfiddle.uk/cEbLp3M4
                continue;
            } elseif (($v === ':x' || $v === ':1') && $this->getDatabasePlatform() instanceof SQLServerPlatform) { // TODO https://dbfiddle.uk/4pDZnwWq
                continue;
            }

            $k = '=' . $k;
            $expected[$v] = $k;
            $query->field($this->e('[]', [$k]), $v);

            if ($v === '\\' || $v === '\\\\' || $v === '\\\\\\') {
                continue;
            }

            if (($v === '"' || $v === '\\\\\\\\') && $this->getDatabasePlatform() instanceof PostgreSQLPlatform) { // https://github.com/php/php-src/issues/13958
                continue;
            } if ($v === '\\\\\\\\' && $this->getDatabasePlatform() instanceof OraclePlatform) { // https://github.com/php/php-src/issues/13958
                continue;
            }

            $k = '\\' . $k;
            $expected['\\' . $v] = $k;
            $query->field($this->e('[]', [$k]), '\\' . $v);
        }

        self::assertSame($expected, $query->getRow());
    }

    public function testUtf8mb4Support(): void
    {
        // MariaDB has no support of utf8mb4 identifiers
        // remove once https://jira.mariadb.org/browse/MDEV-27050 is fixed
        $columnAlias = '❤';
        $tableAlias = '🚀';
        if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform && MysqlConnection::isServerMariaDb($this->getConnection())) {
            $columnAlias = '仮';
            $tableAlias = '名';
        }

        self::assertSame(
            $this->getDatabasePlatform() instanceof AbstractMySQLPlatform && !MysqlConnection::isServerMariaDb($this->getConnection()) && $this->getConnection()->getServerVersion() === '8.0.32'
                ? null
                : [$columnAlias => 'žlutý_😀'],
            $this->q(
                $this->q()->field($this->e('\'žlutý_😀\''), $columnAlias),
                $tableAlias
            )
                ->where($columnAlias, 'žlutý_😀') // as param
                ->group($tableAlias . '.' . $columnAlias)
                ->having($this->e('{}', [$columnAlias])->render()[0] . ' = \'žlutý_😀\'') // as string literal (mapped to N'xxx' with MSSQL platform)
                ->getRow()
        );
    }

    public function testTruncateWithoutPrimaryKey(): void
    {
        $m = new Model($this->db, ['table' => 'without_pk', 'idField' => false]);
        $m->addField('name');
        $this->createMigrator($m)->create();

        $this->q('without_pk')
            ->setMulti(['name' => 'John'])
            ->mode('insert')->executeStatement();

        self::assertSame([
            ['name' => 'John'],
        ], $this->q('without_pk')->getRows());

        $this->q('without_pk')->mode('truncate')->executeStatement();

        self::assertSame([], $this->q('without_pk')->getRows());
    }

    public function testImportAndAutoincrement(): void
    {
        $m = new Model($this->db, ['table' => 'test']);
        $m->getField('id')->actual = 'myid';
        $m->setOrder('id');
        $m->addField('f1');
        $this->createMigrator($m)->create();

        $getLastAiFx = function (): int {
            $table = 'test';
            $pk = 'myid';
            if ($this->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                self::assertFalse($this->getConnection()->inTransaction());
                $this->e('analyze table {}', [$table])->executeStatement();
                $query = $this->q()->table('INFORMATION_SCHEMA.TABLES')
                    ->field($this->e('{} - 1', ['AUTO_INCREMENT']))
                    ->where('TABLE_NAME', $table);
            } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
                $query = $this->q()->field($this->e('currval(pg_get_serial_sequence([], []))', [$table, $pk]));
            } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
                $query = $this->q()->field($this->e('IDENT_CURRENT([])', [$table]));
            } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
                $query = $this->q()->field($this->e('{}.CURRVAL', [$table . '_SEQ']));
            } else {
                $query = $this->q()->table('sqlite_sequence')->field('seq')->where('name', $table);
            }

            return (int) $query->getOne();
        };

        $m->import([
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'B'],
        ]);
        self::assertSame(2, $m->executeCountQuery());
        self::assertSame(2, $getLastAiFx());

        $m->import([
            ['f1' => 'C'],
            ['f1' => 'D'],
        ]);
        self::assertSame(4, $m->executeCountQuery());
        self::assertSame(4, $getLastAiFx());

        $m->import([
            ['id' => 6, 'f1' => 'E'],
            ['id' => 7, 'f1' => 'F'],
        ]);
        self::assertSame(6, $m->executeCountQuery());
        self::assertSame(7, $getLastAiFx());

        $m->delete(6);
        self::assertSame(5, $m->executeCountQuery());
        self::assertSame(7, $getLastAiFx());

        $m->import([
            ['f1' => 'G'],
            ['f1' => 'H'],
        ]);
        self::assertSame(7, $m->executeCountQuery());
        self::assertSame(9, $getLastAiFx());

        $m->import([
            ['id' => 99, 'f1' => 'I'],
            ['id' => 20, 'f1' => 'J'],
        ]);
        self::assertSame(9, $m->executeCountQuery());
        self::assertSame(99, $getLastAiFx());

        $m->import([
            ['f1' => 'K'],
            ['f1' => 'L'],
        ]);
        self::assertSame(11, $m->executeCountQuery());
        self::assertSame(101, $getLastAiFx());

        $m->delete(100);
        $m->createEntity()->set('f1', 'M')->save();
        self::assertSame(102, $getLastAiFx());

        $expectedRows = [
            ['id' => 1, 'f1' => 'A'],
            ['id' => 2, 'f1' => 'B'],
            ['id' => 3, 'f1' => 'C'],
            ['id' => 4, 'f1' => 'D'],
            ['id' => 7, 'f1' => 'F'],
            ['id' => 8, 'f1' => 'G'],
            ['id' => 9, 'f1' => 'H'],
            ['id' => 20, 'f1' => 'J'],
            ['id' => 99, 'f1' => 'I'],
            ['id' => 101, 'f1' => 'L'],
            ['id' => 102, 'f1' => 'M'],
        ];
        self::assertSame($expectedRows, $m->export());

        // auto increment ID after rollback must not be reused
        $invokeInAtomicAndThrowFx = static function (\Closure $fx) use ($m) {
            $e = null;
            $eExpected = new Exception();
            try {
                $m->atomic(static function () use ($fx, $eExpected) {
                    $fx();

                    throw $eExpected;
                });
            } catch (Exception $e) {
            }
            self::assertSame($eExpected, $e);
        };

        $invokeInAtomicAndThrowFx(static function () use ($m) {
            self::assertSame(103, $m->insert(['f1' => 'N']));
        });

        $invokeInAtomicAndThrowFx(static function () use ($invokeInAtomicAndThrowFx, $m) {
            self::assertSame(104, $m->insert(['f1' => 'O1']));
            $invokeInAtomicAndThrowFx(static function () use ($invokeInAtomicAndThrowFx, $m) {
                self::assertSame(105, $m->insert(['f1' => 'O2']));
                self::assertSame(106, $m->insert(['f1' => 'O3']));
                $invokeInAtomicAndThrowFx(static function () use ($m) {
                    self::assertSame(107, $m->insert(['f1' => 'O4']));
                });
            });
            self::assertSame(108, $m->insert(['f1' => 'O5']));
        });

        self::assertSame(108, $getLastAiFx());
        self::assertSame($expectedRows, $m->export());

        self::assertSame(109, $m->insert(['f1' => 'P']));
        self::assertSame(109, $getLastAiFx());
        self::assertSame(array_merge($expectedRows, [
            ['id' => 109, 'f1' => 'P'],
        ]), $m->export());
    }

    public function testAutoincrementAfterDeleteWithoutWhere(): void
    {
        $this->setupTables();

        self::assertSame('4', $this->q('employee')->field($this->e('max({})', ['id']))->getOne());

        $this->q('employee')->mode('delete')->executeStatement();

        $this->q('employee')
            ->setMulti(['name' => 'John'])
            ->mode('insert')->executeStatement();

        $this->q('employee')
            ->setMulti(['name' => 'Jane'])
            ->mode('insert')->executeStatement();

        self::assertSameExportUnordered([
            ['id' => '5', 'name' => 'John'],
            ['id' => '6', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->getRows());
    }

    public function testAutoincrementAfterTruncate(): void
    {
        $this->setupTables();

        self::assertSame('4', $this->q('employee')->field($this->e('max({})', ['id']))->getOne());

        $this->q('employee')->mode('truncate')->executeStatement();

        $this->q('employee')
            ->setMulti(['name' => 'John'])
            ->mode('insert')->executeStatement();

        $this->q('employee')
            ->setMulti(['name' => 'Jane'])
            ->mode('insert')->executeStatement();

        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'John'],
            ['id' => '2', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->getRows());
    }

    public function testOrderDuplicate(): void
    {
        $this->setupTables();

        $query = $this->q('employee')->field('name')
            ->order('id')
            ->order('name', 'desc')
            ->order('name', 'ASC')
            ->order('name')
            ->order('surname')
            ->order('name');

        self::assertSame(
            [['name' => 'Charlie'], ['name' => 'Harry'], ['name' => 'Jack'], ['name' => 'Oliver']],
            $query->getRows()
        );
    }

    public function testSubqueryWithOrderAndLimit(): void
    {
        $this->setupTables();

        $subQuery = $this->q('employee');
        $query = $this->q($subQuery, 't')->field('name')->order('name');

        self::assertSame(
            [['name' => 'Charlie'], ['name' => 'Harry'], ['name' => 'Jack'], ['name' => 'Oliver']],
            $query->getRows()
        );

        // subquery /w limit but /wo order
        $subQuery->limit(2);
        self::assertCount(2, $query->getRows());

        $subQuery->order('surname', true);
        self::assertSame(
            [['name' => 'Harry'], ['name' => 'Jack']],
            $query->getRows()
        );

        // subquery /w order but /wo limit
        $subQuery->args['limit'] = null;
        self::assertSame(
            [['name' => 'Charlie'], ['name' => 'Harry'], ['name' => 'Jack'], ['name' => 'Oliver']],
            $query->getRows()
        );

        self::assertSame([['surname', 'desc']], $subQuery->args['order']);
    }
}
