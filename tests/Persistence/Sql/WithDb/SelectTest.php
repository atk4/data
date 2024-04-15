<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\ExecuteException;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
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
            self::assertSame(
                [['now' => '6']],
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

    public function testOtherQueries(): void
    {
        $this->setupTables();

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

    public function testGetRowEmpty(): void
    {
        $this->setupTables();

        $this->q('employee')->mode('truncate')->executeStatement();
        $q = $this->q('employee');

        self::assertNull($q->getRow());
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
     * @param array{string, array<mixed>} $exprLeft
     * @param array{string, array<mixed>} $exprRight
     */
    #[DataProvider('provideWhereNumericCompareCases')]
    public function testWhereNumericCompare(array $exprLeft, string $operator, array $exprRight, bool $expectPostgresqlTypeMismatchException = false, bool $expectMssqlTypeMismatchException = false): void
    {
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $exprLeft[0] = preg_replace('~\d+[eE][\-+]?\d++~', '$0d', $exprLeft[0]);
        }

        $queryWhere = $this->q()->field($this->e('1'), 'v');
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $queryWhere->table('(select 1)', 'dual'); // needed for MySQL 5.x when WHERE or HAVING is specified
        }
        $queryWhere->where($this->e(...$exprLeft), $operator, $this->e(...$exprRight));

        $queryHaving = $this->q()->field($this->e('1'), 'v');
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
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
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
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

        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
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
            if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
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
        $hasBackslashSupport = $this->getDatabasePlatform() instanceof MySQLPlatform;

        self::assertSame(
            '(?:(?sx)' . "\n"
                . '    \'(?:[^\'' . ($hasBackslashSupport ? '\\\\' : '') . ']+' . ($hasBackslashSupport ? '|\\\.' : '') . '|\'\')*+\'' . "\n"
                . '    |"(?:[^"' . ($hasBackslashSupport ? '\\\\' : '') . ']+' . ($hasBackslashSupport ? '|\\\.' : '') . '|"")*+"' . "\n"
                . '    |`(?:[^`]+|``)*+`' . "\n"
                . '    |\[(?:[^\]]+|\]\])*+\]' . "\n"
                . '    |(?:--|\#)[^\r\n]*+' . "\n"
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
            $needsExplicitAs = $chr === '"' && $this->getDatabasePlatform() instanceof MySQLPlatform;

            if ($chr !== '"' || !$this->getDatabasePlatform() instanceof OraclePlatform) {
                $query = $this->q()->field($this->e('\'x\' ' . ($needsExplicitAs ? 'as ' : '') . $replaceFx($sqlTwoEscape)));
                self::assertSame([$chr => 'x'], $query->getRow());
            }

            $query = $this->q()->field($this->e('\'x\' ' . ($needsExplicitAs ? 'as ' : '') . $replaceFx($sqlBackslashEscape)));
            self::assertSame([$hasBackslashSupport && $chr === '"' ? $chr . '-- ' : '\\' => 'x'], $query->getRow());
        }

        if (!($this->getDatabasePlatform() instanceof MySQLPlatform || $this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof OraclePlatform)) {
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

        // TODO full binary support
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform
            || $this->getDatabasePlatform() instanceof SQLServerPlatform
            || $this->getDatabasePlatform() instanceof OraclePlatform
        ) {
            $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
        }

        // Oracle string literal is limited to 4000 bytes
        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $str = substr($str, 0, 4000);
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
            '\\n',
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
            if ($v === '2' && ( // TODO report php-src issue
                $this->getDatabasePlatform() instanceof SQLitePlatform // https://dbfiddle.uk/XLSbYEoC
                || $this->getDatabasePlatform() instanceof MySQLPlatform // https://dbfiddle.uk/NAEJ_B3u https://dbfiddle.uk/vLxZ3sqz
                || $this->getDatabasePlatform() instanceof PostgreSQLPlatform // https://dbfiddle.uk/h2CWtcWk
                || $this->getDatabasePlatform() instanceof SQLServerPlatform // https://dbfiddle.uk/M1JTBjMd
                || $this->getDatabasePlatform() instanceof OraclePlatform // https://dbfiddle.uk/P9Z3SZ0k
            )) {
                continue;
            } elseif ($v === '"' && $this->getDatabasePlatform() instanceof OraclePlatform) { // Oracle identifier cannot contain double quote
                continue;
            } elseif (($v === '\\' || $v === '\\\\\\') && ($this->getDatabasePlatform() instanceof PostgreSQLPlatform || $this->getDatabasePlatform() instanceof OraclePlatform)) { // https://github.com/php/php-src/issues/13958
                continue;
            } elseif (($v === '?' || $v === ':x' || $v === ':1' || $v === '--') && $this->getDatabasePlatform() instanceof MySQLPlatform) { // TODO pdo_mysql only https://dbfiddle.uk/cEbLp3M4
                continue;
            } elseif (($v === ':x' || $v === ':1') && $this->getDatabasePlatform() instanceof SQLServerPlatform) { // TODO https://dbfiddle.uk/4pDZnwWq
                continue;
            }

            $k = '=' . $k;
            $expected[$v] = $k;
            $query->field($this->e('[]', [$k]), $v);

            if (($v === '"' || $v === '\\\\\\\\') && $this->getDatabasePlatform() instanceof PostgreSQLPlatform) { // https://github.com/php/php-src/issues/13958
                continue;
            } if ($v === '\\\\\\\\' && $this->getDatabasePlatform() instanceof OraclePlatform) { // https://github.com/php/php-src/issues/13958
                continue;
            } elseif (($v === '\\' || $v === '\\\\' || $v === '\\\\\\') && ( // TODO report php-src issue
                $this->getDatabasePlatform() instanceof SQLitePlatform // https://dbfiddle.uk/ye6Jv9AW
                || $this->getDatabasePlatform() instanceof MySQLPlatform // https://dbfiddle.uk/cyoglskt
                || $this->getDatabasePlatform() instanceof PostgreSQLPlatform // https://dbfiddle.uk/1L18Yn42
                || $this->getDatabasePlatform() instanceof SQLServerPlatform // https://dbfiddle.uk/3MchbP0_
                || $this->getDatabasePlatform() instanceof OraclePlatform // https://dbfiddle.uk/8veckcQK
            )) {
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
        if (str_contains($_ENV['DB_DSN'], 'mariadb')) {
            $columnAlias = '仮';
            $tableAlias = '名';
        }

        self::assertSame(
            [$columnAlias => 'žlutý_😀'],
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
            if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
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
