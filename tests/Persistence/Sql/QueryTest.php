<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Mssql\Query as MssqlQuery;
use Atk4\Data\Persistence\Sql\Mysql\Connection as MysqlConnection;
use Atk4\Data\Persistence\Sql\Mysql\Expression as MysqlExpression;
use Atk4\Data\Persistence\Sql\Mysql\Query as MysqlQuery;
use Atk4\Data\Persistence\Sql\Oracle\Query as OracleQuery;
use Atk4\Data\Persistence\Sql\Postgresql\Query as PostgresqlQuery;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Persistence\Sql\Sqlite\Connection as SqliteConnection;
use Atk4\Data\Persistence\Sql\Sqlite\Query as SqliteQuery;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;

class QueryTest extends TestCase
{
    /**
     * @param string|array<string, mixed> $template
     * @param array<int|string, mixed>    $arguments
     */
    protected function q($template = [], array $arguments = []): Query
    {
        $query = new class($template, $arguments) extends Query {
            protected string $identifierEscapeChar = '"';

            /**
             * @param array<string, mixed>     $defaults
             * @param array<int|string, mixed> $arguments
             */
            public function __construct($defaults = [], array $arguments = [])
            {
                $this->expressionClass = get_class(new class() extends Expression {
                    protected string $identifierEscapeChar = '"';

                    #[\Override]
                    protected function escapeStringLiteral(string $value): string
                    {
                        return null; // @phpstan-ignore-line
                    }
                });

                parent::__construct($defaults, $arguments);
            }

            #[\Override]
            protected function escapeStringLiteral(string $value): string
            {
                return '\'' . str_replace('\'', '\'\'', $value) . '\'';
            }
        };

        if (($query->connection ?? null) === null) {
            $query->connection = \Closure::bind(static function () use ($query) {
                $connection = new SqliteConnection();
                $connection->expressionClass = \Closure::bind(static fn () => $query->expressionClass, null, Query::class)();
                $connection->queryClass = get_class($query);

                return $connection;
            }, null, Connection::class)();
        }

        return $query;
    }

    /**
     * @param string|array<string, mixed> $template
     * @param array<int|string, mixed>    $arguments
     */
    protected function e($template = [], array $arguments = []): Expression
    {
        return $this->q()->expr($template, $arguments);
    }

    /**
     * @param mixed ...$args
     *
     * @return mixed
     */
    private function callProtected(object $obj, string $name, ...$args)
    {
        return \Closure::bind(static fn () => $obj->{$name}(...$args), null, $obj)();
    }

    public function testConstruct(): void
    {
        self::assertSame(
            '"q"',
            $this->callProtected($this->q(), 'escapeIdentifier', 'q')
        );
    }

    public function testExpr(): void
    {
        self::assertInstanceOf(Expression::class, $this->q()->expr('foo'));

        $connection = \Closure::bind(static fn () => new MysqlConnection(), null, Connection::class)();
        $q = new MysqlQuery(['connection' => $connection]);
        self::assertSame(MysqlExpression::class, get_class($q->expr('foo')));
        self::assertSame($connection, $q->expr('foo')->connection);
    }

    public function testDsql(): void
    {
        self::assertInstanceOf(Query::class, $this->q()->dsql());

        $connection = \Closure::bind(static fn () => new MysqlConnection(), null, Connection::class)();
        $q = new MysqlQuery(['connection' => $connection]);
        self::assertSame(MysqlQuery::class, get_class($q->dsql()));
        self::assertSame($connection, $q->dsql()->connection);
    }

    public function testFieldReturnThis(): void
    {
        $q = $this->q();
        self::assertSame($q, $q->field('first_name'));
    }

    public function testFieldBasic(): void
    {
        self::assertSame(
            '"first_name"',
            $this->callProtected($this->q()->field('first_name'), '_renderField')
        );
        self::assertSame(
            '"first_name", "last_name"',
            $this->callProtected($this->q()->field('first_name')->field('last_name'), '_renderField')
        );
        self::assertSame(
            '"last_name"',
            $this->callProtected($this->q()->field('first_name')->reset('field')->field('last_name'), '_renderField')
        );
        self::assertSame(
            '*',
            $this->callProtected($this->q()->field('first_name')->reset('field'), '_renderField')
        );
        self::assertSame(
            '*',
            $this->callProtected($this->q()->field('first_name')->reset(), '_renderField')
        );
        self::assertSame(
            '"employee"."first_name"',
            $this->callProtected($this->q()->field('employee.first_name'), '_renderField')
        );
        self::assertSame(
            '"first_name" "name"',
            $this->callProtected($this->q()->field('first_name', 'name'), '_renderField')
        );
        self::assertSame(
            '"first_name" "2"',
            $this->callProtected($this->q()->field('first_name', '2'), '_renderField')
        );
        self::assertSame(
            '*',
            $this->callProtected($this->q()->field('*'), '_renderField')
        );
        self::assertSame(
            '"employee"."first_name"',
            $this->callProtected($this->q()->field('employee.first_name'), '_renderField')
        );
    }

    public function testFieldDefaultField(): void
    {
        self::assertSame(
            '*',
            $this->callProtected($this->q(), '_renderField')
        );
        self::assertSame(
            '"id"',
            $this->callProtected($this->q(['defaultField' => 'id']), '_renderField')
        );
        self::assertSame(
            '"all"."values"',
            $this->callProtected($this->q(['defaultField' => 'all.values']), '_renderField')
        );
        self::assertSame(
            '"all".*',
            $this->callProtected($this->q(['defaultField' => 'all.*']), '_renderField')
        );
    }

    public function testFieldExpression(): void
    {
        self::assertSame(
            '"name"',
            $this->q('[field]')->field('name')->render()[0]
        );
        self::assertSame(
            '"first name"',
            $this->q('[field]')->field('first name')->render()[0]
        );
        self::assertSame(
            '"first"."name"',
            $this->q('[field]')->field('first.name')->render()[0]
        );
        self::assertSame(
            'now()',
            $this->q('[field]')->field('now()')->render()[0]
        );
        self::assertSame(
            'now()',
            $this->q('[field]')->field($this->e('now()'))->render()[0]
        );
        // usage of field aliases
        self::assertSame(
            'now() "time"',
            $this->q('[field]')->field('now()', 'time')->render()[0]
        );
        self::assertSame(// alias can be passed as 2nd argument
            'now() "time"',
            $this->q('[field]')->field($this->e('now()'), 'time')->render()[0]
        );
    }

    public function testFieldDuplicateAliasException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be unique');
        $this->q()->field('name', 'a')->field('surname', 'a');
    }

    /**
     * @doesNotPerformAssertions
     */
    #[DoesNotPerformAssertions]
    public function testTableNoAliasExpression(): void
    {
        $this->q()->table($this->q()->expr('test'));
    }

    public function testTableNoAliasQueryException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Table alias is required when table is set as subquery');
        $this->q()->table($this->q()->table('test'));
    }

    public function testTableAliasNotUniqueException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be unique');
        $this->q()
            ->table('foo', 'a')
            ->table('bar', 'a');
    }

    public function testTableAliasNotUniqueException2(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be unique');
        $this->q()
            ->table('foo', 'bar')
            ->table('bar');
    }

    public function testTableAliasNotUniqueException3(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be unique');
        $this->q()
            ->table('foo')
            ->table('foo');
    }

    public function testTableAliasNotUniqueException4(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be unique');
        $this->q()
            ->table($this->q()->table('test'), 'foo')
            ->table('foo');
    }

    public function testTableAliasNotUniqueException5(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be unique');
        $this->q()
            ->table('foo')
            ->table($this->q()->table('test'), 'foo');
    }

    public function testTableAliasIntStringException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Alias must be not int-string');
        $this->q()->table('foo', '10');
    }

    /**
     * Table can't be set as sub-Query in Update query mode.
     */
    public function testTableException10(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->mode('update')
            ->table($this->q()->table('test'), 'foo')
            ->set('name', 1)
            ->render();
    }

    /**
     * Table can't be set as sub-Query in Insert query mode.
     */
    public function testTableException11(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->mode('insert')
            ->table($this->q()->table('test'), 'foo')
            ->set('name', 1)
            ->render();
    }

    /**
     * Requesting non-existent query mode should throw exception.
     */
    public function testModeException1(): void
    {
        $this->expectException(Exception::class);
        $this->q()->mode('non_existent_mode');
    }

    public function testTableReturnThis(): void
    {
        $q = $this->q();
        self::assertSame($q, $q->table('employee'));
    }

    public function testTableRender1(): void
    {
        // no table defined
        self::assertSame(
            'select now()',
            $this->q()
                ->field($this->e('now()'))
                ->render()[0]
        );

        // one table
        self::assertSame(
            'select "name" from "employee"',
            $this->q()
                ->field('name')->table('employee')
                ->render()[0]
        );

        self::assertSame(
            'select "na#me" from "employee"',
            $this->q()
                ->field('"na#me"')->table('employee')
                ->render()[0]
        );
        self::assertSame(
            'select "na""me" from "employee"',
            $this->q()
                ->field($this->e('{}', ['na"me']))->table('employee')
                ->render()[0]
        );
        self::assertSame(
            'select "жук" from "employee"',
            $this->q()
                ->field($this->e('{}', ['жук']))->table('employee')
                ->render()[0]
        );
        self::assertSame(
            'select "this is 💩" from "employee"',
            $this->q()
                ->field($this->e('{}', ['this is 💩']))->table('employee')
                ->render()[0]
        );

        self::assertSame(
            'select "name" from "employee" "e"',
            $this->q()
                ->field('name')->table('employee', 'e')
                ->render()[0]
        );
        self::assertSame(
            'select * from "employee" "e"',
            $this->q()
                ->table('employee', 'e')
                ->render()[0]
        );

        // multiple tables
        self::assertSame(
            'select "employee"."name" from "employee", "jobs"',
            $this->q()
                ->field('employee.name')->table('employee')->table('jobs')
                ->render()[0]
        );

        // multiple tables with aliases
        self::assertSame(
            'select "name" from "employee", "jobs" "j"',
            $this->q()
                ->field('name')->table('employee')->table('jobs', 'j')
                ->render()[0]
        );
        self::assertSame(
            'select "name" from "employee" "e", "jobs" "j"',
            $this->q()
                ->field('name')->table('employee', 'e')->table('jobs', 'j')
                ->render()[0]
        );
        // testing _renderTableNoalias, shouldn't render table alias 'emp'
        self::assertSame(
            'insert into "employee" ("name") values (:a)',
            $this->q()
                ->table('employee', 'emp')->set('name', 1)
                ->mode('insert')
                ->render()[0]
        );
        self::assertSame(
            'update "employee" set "name"=:a',
            $this->q()
                ->table('employee', 'emp')->set('name', 1)
                ->mode('update')
                ->render()[0]
        );
    }

    public function testTableRender2(): void
    {
        // pass table as expression or query
        $q = $this->q()->table('employee');

        self::assertSame(
            'select "name" from (select * from "employee") "e"',
            $this->q()
                ->field('name')->table($q, 'e')
                ->render()[0]
        );

        self::assertSame(
            'select "name" from "myt""able"',
            $this->q()
                ->field('name')->table($this->e('{}', ['myt"able']))
                ->render()[0]
        );

        // test with multiple sub-queries as tables
        $q1 = $this->q()->table('employee');
        $q2 = $this->q()->table('customer');

        self::assertSame(
            // this way it would be more correct:
            // 'select "e"."name", "c"."name" from (select * from "employee") "e", (select * from "customer") "c" where "e"."last_name" = "c"."last_name"',
            'select "e"."name", "c"."name" from (select * from "employee") "e", (select * from "customer") "c" where "e"."last_name" = c.last_name',
            $this->q()
                ->field('e.name')
                ->field('c.name')
                ->table($q1, 'e')
                ->table($q2, 'c')
                ->where('e.last_name', $this->q()->expr('c.last_name'))
                ->render()[0]
        );
    }

    public function testBasicRenderSubquery(): void
    {
        $age = $this->e('coalesce([age], [default_age])');
        $age['age'] = $this->e('year(now()) - year(birth_date)');
        $age['default_age'] = 18;

        $q = $this->q()->table('user')->field($age, 'calculated_age');

        self::assertSame(
            'select coalesce(year(now()) - year(birth_date), :a) "calculated_age" from "user"',
            $q->render()[0]
        );
    }

    public function testGetDebugQuery(): void
    {
        $age = $this->e('coalesce([age], [default_age], [foo], [bar])');
        $age['age'] = $this->e('year(now()) - year(birth_date)');
        $age['default_age'] = 18;
        $age['foo'] = 'foo';
        $age['bar'] = null;

        $q = $this->q()->table('user')->field($age, 'calculated_age');

        self::assertSame(
            preg_replace('~\s+~', '', 'select coalesce(year(now()) - year(birth_date), 18, \'foo\', NULL) "calculated_age" from "user"'),
            preg_replace('~\s+~', '', $q->getDebugQuery())
        );
    }

    public function testVarDumpBasic(): void
    {
        self::assertMatchesRegularExpression(
            '~^select\s+\*\s+from\s*"user"$~',
            $this->q()->table('user')->__debugInfo()['R']
        );
    }

    public function testVarDumpException(): void
    {
        self::assertSame(
            Exception::class . ': Table cannot be Query in UPDATE, INSERT etc. query modes',
            $this->q()
                ->mode('update')
                ->table($this->q()->table('test'), 'foo')->__debugInfo()['R']
        );
    }

    public function testUnionQuery(): void
    {
        // 1st query
        $q1 = $this->q()
            ->table('sales')
            ->field('date')
            ->field('amount', 'debit')
            ->field($this->q()->expr('0'), 'credit'); // simply 0
        self::assertSame(
            'select "date", "amount" "debit", 0 "credit" from "sales"',
            $q1->render()[0]
        );

        // 2nd query
        $q2 = $this->q()
            ->table('purchases')
            ->field('date')
            ->field($this->q()->expr('0'), 'debit') // simply 0
            ->field('amount', 'credit');
        self::assertSame(
            'select "date", 0 "debit", "amount" "credit" from "purchases"',
            $q2->render()[0]
        );

        // $q1 union $q2
        $u = $this->e('([] union [])', [$q1, $q2]);
        self::assertSame(
            '((select "date", "amount" "debit", 0 "credit" from "sales") union (select "date", 0 "debit", "amount" "credit" from "purchases"))',
            $u->render()[0]
        );

        // SELECT date, debit, credit FROM ($q1 union $q2)
        $q = $this->q()
            ->field('date')
            ->field('debit')
            ->field('credit')
            ->table($u, 'derivedTable');
        self::assertSame(
            'select "date", "debit", "credit" from ((select "date", "amount" "debit", 0 "credit" from "sales") union (select "date", 0 "debit", "amount" "credit" from "purchases")) "derivedTable"',
            $q->render()[0]
        );

        // SQLite do not support (($q1) union ($q2)) syntax. Correct syntax is ($q1 union $q2) without additional braces,
        // other SQL engines are more relaxed, but still these additional braces are not needed for union
        $q1->wrapInParentheses = false;
        $q2->wrapInParentheses = false;
        $u = $this->e('([] union [])', [$q1, $q2]);
        self::assertSame(
            '(select "date", "amount" "debit", 0 "credit" from "sales" union select "date", 0 "debit", "amount" "credit" from "purchases")',
            $u->render()[0]
        );

        // SELECT date, debit, credit FROM ($q1 union $q2)
        $q = $this->q()
            ->field('date')
            ->field('debit')
            ->field('credit')
            ->table($u, 'derivedTable');
        self::assertSame(
            'select "date", "debit", "credit" from (select "date", "amount" "debit", 0 "credit" from "sales" union select "date", 0 "debit", "amount" "credit" from "purchases") "derivedTable"',
            $q->render()[0]
        );
    }

    public function testWhereReturnThis(): void
    {
        $q = $this->q();
        self::assertSame($q, $q->where('id', 1));
    }

    public function testHavingReturnThis(): void
    {
        $q = $this->q();
        self::assertSame($q, $q->having('id', 1));
    }

    public function testWhereBasic(): void
    {
        // one parameter as a string - treat as expression
        self::assertSame(
            'where (now())',
            $this->q('[where]')->where('now()')->render()[0]
        );
        self::assertSame(
            'where (foo >=    bar)',
            $this->q('[where]')->where('foo >=    bar')->render()[0]
        );

        // two parameters - field, value
        self::assertSame(
            'where "id" = :a',
            $this->q('[where]')->where('id', 1)->render()[0]
        );
        self::assertSame(
            'where "user"."id" = :a',
            $this->q('[where]')->where('user.id', 1)->render()[0]
        );
        self::assertSame(
            'where "db"."user"."id" = :a',
            $this->q('[where]')->where('db.user.id', 1)->render()[0]
        );
        self::assertSame(
            'where "id" is null',
            $this->q('[where]')->where('id', null)->render()[0]
        );
        self::assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', '!=', null)->render()[0]
        );
        self::assertSame(
            'where case when typeof(`id`) in (\'integer\', \'real\') then cast(`id` as numeric) = :a else case when typeof(:a) in (\'integer\', \'real\') then `id` = cast(:a as numeric) else `id` = :a end end',
            (new SqliteQuery('[where]'))->where('id', 1)->render()[0]
        );

        // three parameters - field, condition, value
        self::assertSame(
            'where "id" > :a',
            $this->q('[where]')->where('id', '>', 1)->render()[0]
        );
        self::assertSame(
            'where "id" < :a',
            $this->q('[where]')->where('id', '<', 1)->render()[0]
        );
        self::assertSame(
            'where "id" = :a',
            $this->q('[where]')->where('id', '=', 1)->render()[0]
        );
        self::assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', 'in', [1, 2])->render()[0]
        );
        self::assertSame(
            'where "id" in (select * from "user")',
            $this->q('[where]')->where('id', $this->q()->table('user'))->render()[0]
        );

        // field name with special symbols - not escape
        self::assertSame(
            'where now() = :a',
            $this->q('[where]')->where('now()', 1)->render()[0]
        );

        // field name as expression
        self::assertSame(
            'where now = :a',
            $this->q('[where]')->where($this->e('now'), 1)->render()[0]
        );
        self::assertSame(
            version_compare(SqliteConnection::getDriverVersion(), '3.45') < 0
                ? 'where case when typeof(sum("id")) in (\'integer\', \'real\') then cast(sum("id") as numeric) = :a else case when typeof(:a) in (\'integer\', \'real\') then sum("id") = cast(:a as numeric) else sum("id") = :a end end'
                : 'where (select case when typeof(`__atk4_affinity_left__`) in (\'integer\', \'real\') then cast(`__atk4_affinity_left__` as numeric) = :a else case when typeof(:a) in (\'integer\', \'real\') then `__atk4_affinity_left__` = cast(:a as numeric) else `__atk4_affinity_left__` = :a end end from (select sum("id") `__atk4_affinity_left__`) `__atk4_affinity_tmp__`)',
            (new SqliteQuery('[where]'))->where($this->e('sum({})', ['id']), 1)->render()[0]
        );
        self::assertSame(
            version_compare(SqliteConnection::getDriverVersion(), '3.45') < 0
                ? 'where case when typeof(sum("id")) in (\'integer\', \'real\') then cast(sum("id") as numeric) = sum("b") else case when typeof(sum("b")) in (\'integer\', \'real\') then sum("id") = cast(sum("b") as numeric) else sum("id") = sum("b") end end'
                : 'where (select case when typeof(`__atk4_affinity_left__`) in (\'integer\', \'real\') then cast(`__atk4_affinity_left__` as numeric) = `__atk4_affinity_right__` else case when typeof(`__atk4_affinity_right__`) in (\'integer\', \'real\') then `__atk4_affinity_left__` = cast(`__atk4_affinity_right__` as numeric) else `__atk4_affinity_left__` = `__atk4_affinity_right__` end end from (select sum("id") `__atk4_affinity_left__`, sum("b") `__atk4_affinity_right__`) `__atk4_affinity_tmp__`)',
            (new SqliteQuery('[where]'))->where($this->e('sum({})', ['id']), $this->e('sum({})', ['b']))->render()[0]
        );

        // more than one where condition - join with AND keyword
        self::assertSame(
            'where "a" = :a and "b" is null',
            $this->q('[where]')->where('a', 1)->where('b', null)->render()[0]
        );
    }

    public function testWhereExpression(): void
    {
        self::assertSame(
            'where (a = 5 or b = 6) and (c = 3 or d = 1)',
            $this->q('[where]')->where('a = 5 or b = 6')->where('c = 3 or d = 1')->render()[0]
        );
    }

    public function testWhereIncompatibleFieldWithCondition(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('id=', 1);
    }

    public function testWhereIncompatibleObject1(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('a', new \DateTime());
    }

    public function testWhereIncompatibleObject2(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('a', '=', new \DateTime());
    }

    public function testWhereIncompatibleObject3(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('a', '!=', new \DateTime());
    }

    /**
     * @param mixed $value
     *
     * @dataProvider provideWhereUnsupportedOperatorCases
     */
    #[DataProvider('provideWhereUnsupportedOperatorCases')]
    public function testWhereUnsupportedOperator(string $operator, $value): void
    {
        $q = $this->q('[where]')->where('x', $operator, $value);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported operator');
        $q->render();
    }

    /**
     * @return iterable<list<mixed>>
     */
    public static function provideWhereUnsupportedOperatorCases(): iterable
    {
        // unsupported operators
        yield ['<>', 2];
        yield ['op', 2];

        yield ['is', null];
        yield ['is not', null];
        yield ['is', true];

        yield ['not', null];
        yield ['not', 2];
        yield ['not', [1, 2]];

        // unsupported operators with specific value type
        yield ['>', null];
        yield ['=', [1, 2]];
        yield ['!=', [1, 2]];
        yield ['=', []];
        yield ['!=', []];
        yield ['in', '1'];
        yield ['in', '1,2'];
        yield ['in', '1, 2'];
        yield ['not in', '1;2'];
        yield ['in', null];
    }

    /**
     * @param string|array<string, mixed> $template
     */
    private function createMysqlQuery(string $serverVersion, $template = []): MysqlQuery
    {
        $dbalConnection = new class($serverVersion) extends DbalConnection {
            private string $serverVersion;

            public function __construct(string $serverVersion) // @phpstan-ignore-line
            {
                $this->serverVersion = $serverVersion;
            }

            #[\Override]
            public function getWrappedConnection()
            {
                return new class($this->serverVersion) extends AbstractConnectionMiddleware {
                    private string $serverVersion;

                    public function __construct(string $serverVersion) // @phpstan-ignore-line
                    {
                        $this->serverVersion = $serverVersion;
                    }

                    #[\Override]
                    public function getServerVersion()
                    {
                        return $this->serverVersion;
                    }
                };
            }

            #[\Override]
            public function getDatabasePlatform()
            {
                return new MySQLPlatform();
            }
        };

        $connection = \Closure::bind(static fn () => new MysqlConnection(), null, Connection::class)();
        \Closure::bind(static fn () => $connection->_connection = $dbalConnection, null, Connection::class)();

        $q = new MysqlQuery($template);
        $q->connection = $connection;

        return $q;
    }

    public function testWhereSpecialValues(): void
    {
        // in | not in
        self::assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', 'in', [1, 2])->render()[0]
        );
        self::assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', 'not in', [1, 2])->render()[0]
        );
        self::assertSame(
            'where ('
                . 'case when typeof(`id`) in (\'integer\', \'real\') then cast(`id` as numeric) = :a else case when typeof(:a) in (\'integer\', \'real\') then `id` = cast(:a as numeric) else `id` = :a end end'
                . ' or '
                . 'case when typeof(`id`) in (\'integer\', \'real\') then cast(`id` as numeric) = :b else case when typeof(:b) in (\'integer\', \'real\') then `id` = cast(:b as numeric) else `id` = :b end end'
                . ')',
            (new SqliteQuery('[where]'))->where('id', 'in', [1, 2])->render()[0]
        );
        // special treatment for empty array values
        self::assertSame(
            'where 1 = 0',
            $this->q('[where]')->where('id', 'in', [])->render()[0]
        );
        self::assertSame(
            'where 1 = 1',
            $this->q('[where]')->where('id', 'not in', [])->render()[0]
        );

        // is null | is not null
        self::assertSame(
            'where "id" is null',
            $this->q('[where]')->where('id', '=', null)->render()[0]
        );
        self::assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', '!=', null)->render()[0]
        );

        // like | not like
        self::assertSame(
            <<<'EOF'
                where "name" like regexp_replace(:a, '(\\[\\_%])|(\\)', '\1\2\2') escape '\'
                EOF,
            $this->q('[where]')->where('name', 'like', 'foo')->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where "name" not like regexp_replace(:a, '(\\[\\_%])|(\\)', '\1\2\2') escape '\'
                EOF,
            $this->q('[where]')->where('name', 'not like', 'foo')->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where case when case when instr(:a, '_') != 0 then 1 else `name` like regexp_replace(:a, '(\\[\\_%])|(\\)', '\1\2\2') escape '\' end then regexp_like(`name`, concat('^',regexp_replace(regexp_replace(regexp_replace(regexp_replace(:a, '\\(?:(?=[_%])|\K\\)|(?=[.\\+*?[^\]$(){}|])', '\'), '(?<!\\)(\\\\)*\K_', '.'), '(?<!\\)(\\\\)*\K%', '.*'), '(?<!\\)(\\\\)*\K\\(?=[_%])', ''), '$'), case when (select __atk4_case_v__ = 'a' from (select `name` __atk4_case_v__ where 0 union all select 'A') __atk4_case_tmp__) then 'is' else '-us' end) when `name` is not null and :a is not null then 0 end
                EOF,
            (new SqliteQuery('[where]'))->where('name', 'like', 'foo')->render()[0]
        );
        self::assertSame(
            version_compare(SqliteConnection::getDriverVersion(), '3.45') < 0
                ? <<<'EOF'
                    where case when case when instr(sum("b"), '_') != 0 then 1 else sum("a") like regexp_replace(sum("b"), '(\\[\\_%])|(\\)', '\1\2\2') escape '\' end then regexp_like(sum("a"), concat('^',regexp_replace(regexp_replace(regexp_replace(regexp_replace(sum("b"), '\\(?:(?=[_%])|\K\\)|(?=[.\\+*?[^\]$(){}|])', '\'), '(?<!\\)(\\\\)*\K_', '.'), '(?<!\\)(\\\\)*\K%', '.*'), '(?<!\\)(\\\\)*\K\\(?=[_%])', ''), '$'), case when (select __atk4_case_v__ = 'a' from (select sum("a") __atk4_case_v__ where 0 union all select 'A') __atk4_case_tmp__) then 'is' else '-us' end) when sum("a") is not null and sum("b") is not null then 0 end
                    EOF
                : <<<'EOF'
                    where (select case when case when instr(`__atk4_reuse_right__`, '_') != 0 then 1 else `__atk4_reuse_left__` like regexp_replace(`__atk4_reuse_right__`, '(\\[\\_%])|(\\)', '\1\2\2') escape '\' end then regexp_like(`__atk4_reuse_left__`, concat('^',regexp_replace(regexp_replace(regexp_replace(regexp_replace(`__atk4_reuse_right__`, '\\(?:(?=[_%])|\K\\)|(?=[.\\+*?[^\]$(){}|])', '\'), '(?<!\\)(\\\\)*\K_', '.'), '(?<!\\)(\\\\)*\K%', '.*'), '(?<!\\)(\\\\)*\K\\(?=[_%])', ''), '$'), case when (select __atk4_case_v__ = 'a' from (select `__atk4_reuse_left__` __atk4_case_v__ where 0 union all select 'A') __atk4_case_tmp__) then 'is' else '-us' end) when `__atk4_reuse_left__` is not null and `__atk4_reuse_right__` is not null then 0 end from (select sum("a") `__atk4_reuse_left__`, sum("b") `__atk4_reuse_right__`) `__atk4_reuse_tmp__`)
                    EOF,
            (new SqliteQuery('[where]'))->where($this->e('sum({})', ['a']), 'like', $this->e('sum({})', ['b']))->render()[0]
        );
        foreach (['5.7.0', '8.0.0', 'MariaDB-11.0.0'] as $serverVersion) {
            self::assertSame(
                $serverVersion === '5.7.0'
                    ? <<<'EOF'
                        where `name` like replace(replace(replace(replace(replace(replace(replace(replace(:a, '\\\\', '\\\\*'), '\\_', '\\_*'), '\\%', '\\%*'), '\\', '\\\\'), '\\\\_*', '\\_'), '\\\\%*', '\\%'), '\\\\\\\\*', '\\\\'), '%\\', '%\\\\') escape '\\'
                        EOF
                    : <<<'EOF'
                        where `name` like regexp_replace(:a, '\\\\\\\\|\\\\(?![_%])', '\\\\\\\\') escape '\\'
                        EOF,
                $this->createMysqlQuery($serverVersion, '[where]')->where('name', 'like', 'foo')->render()[0]
            );
        }
        self::assertSame(
            <<<'EOF'
                where case when pg_typeof("name") = 'bytea'::regtype then replace(regexp_replace(encode(case when pg_typeof("name") = 'bytea'::regtype then decode(case when pg_typeof("name") = 'bytea'::regtype then replace(substring(cast("name" as text) from 3), chr(92), repeat(chr(92), 2)) else '' end, 'hex') else cast(replace(cast("name" as text), chr(92), repeat(chr(92), 2)) as bytea) end, 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)) like regexp_replace(replace(regexp_replace(encode(cast(replace(cast(:a as text), chr(92), repeat(chr(92), 2)) as bytea), 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)), '(\\[\\_%])|(\\)', '\1\2\2', 'g') escape chr(92) else cast("name" as citext) like regexp_replace(:a, '(\\[\\_%])|(\\)', '\1\2\2', 'g') escape chr(92) end
                EOF,
            (new PostgresqlQuery('[where]'))->where('name', 'like', 'foo')->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where (select case when pg_typeof("__atk4_reuse_left__") = 'bytea'::regtype then replace(regexp_replace(encode(case when pg_typeof("__atk4_reuse_left__") = 'bytea'::regtype then decode(case when pg_typeof("__atk4_reuse_left__") = 'bytea'::regtype then replace(substring(cast("__atk4_reuse_left__" as text) from 3), chr(92), repeat(chr(92), 2)) else '' end, 'hex') else cast(replace(cast("__atk4_reuse_left__" as text), chr(92), repeat(chr(92), 2)) as bytea) end, 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)) like regexp_replace(replace(regexp_replace(encode(cast(replace(cast("__atk4_reuse_right__" as text), chr(92), repeat(chr(92), 2)) as bytea), 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)), '(\\[\\_%])|(\\)', '\1\2\2', 'g') escape chr(92) else cast("__atk4_reuse_left__" as citext) like regexp_replace("__atk4_reuse_right__", '(\\[\\_%])|(\\)', '\1\2\2', 'g') escape chr(92) end from (select sum("a") "__atk4_reuse_left__", sum("b") "__atk4_reuse_right__") "__atk4_reuse_tmp__")
                EOF,
            (new PostgresqlQuery('[where]'))->where($this->e('sum({})', ['a']), 'like', $this->e('sum({})', ['b']))->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where ((datalength(concat((select top 0 [name]), 0x30)) = 2 and [name] like replace(replace(replace(replace(replace(replace(replace(replace(:a, N'\\', N'\\*'), N'\_', N'\_*'), N'\%', N'\%*'), N'\', N'\\'), N'\\_*', N'\_'), N'\\%*', N'\%'), N'\\\\*', N'\\'), N'[', N'\[') escape N'\') or (datalength(concat((select top 0 [name]), 0x30)) != 2 and ((isnull((select top 0 [name]), 0x41) != 0x61 and [name] like replace(replace(replace(replace(replace(replace(replace(replace(:a, 0x5c5c, 0x5c5c2a), 0x5c5f, 0x5c5f2a), 0x5c25, 0x5c252a), 0x5c, 0x5c5c), 0x5c5c5f2a, 0x5c5f), 0x5c5c252a, 0x5c25), 0x5c5c5c5c2a, 0x5c5c), 0x5b, 0x5c5b) collate Latin1_General_BIN escape 0x5c) or (isnull((select top 0 [name]), 0x41) = 0x61 and [name] like replace(replace(replace(replace(replace(replace(replace(replace(:a, 0x5c5c, 0x5c5c2a), 0x5c5f, 0x5c5f2a), 0x5c25, 0x5c252a), 0x5c, 0x5c5c), 0x5c5c5f2a, 0x5c5f), 0x5c5c252a, 0x5c25), 0x5c5c5c5c2a, 0x5c5c), 0x5b, 0x5c5b) escape 0x5c))))
                EOF,
            (new MssqlQuery('[where]'))->where('name', 'like', 'foo')->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where (select iif(not(((datalength(concat((select top 0 [__atk4_reuse_left__]), 0x30)) = 2 and [__atk4_reuse_left__] like replace(replace(replace(replace(replace(replace(replace(replace([__atk4_reuse_right__], N'\\', N'\\*'), N'\_', N'\_*'), N'\%', N'\%*'), N'\', N'\\'), N'\\_*', N'\_'), N'\\%*', N'\%'), N'\\\\*', N'\\'), N'[', N'\[') escape N'\') or (datalength(concat((select top 0 [__atk4_reuse_left__]), 0x30)) != 2 and ((isnull((select top 0 [__atk4_reuse_left__]), 0x41) != 0x61 and [__atk4_reuse_left__] like replace(replace(replace(replace(replace(replace(replace(replace([__atk4_reuse_right__], 0x5c5c, 0x5c5c2a), 0x5c5f, 0x5c5f2a), 0x5c25, 0x5c252a), 0x5c, 0x5c5c), 0x5c5c5f2a, 0x5c5f), 0x5c5c252a, 0x5c25), 0x5c5c5c5c2a, 0x5c5c), 0x5b, 0x5c5b) collate Latin1_General_BIN escape 0x5c) or (isnull((select top 0 [__atk4_reuse_left__]), 0x41) = 0x61 and [__atk4_reuse_left__] like replace(replace(replace(replace(replace(replace(replace(replace([__atk4_reuse_right__], 0x5c5c, 0x5c5c2a), 0x5c5f, 0x5c5f2a), 0x5c25, 0x5c252a), 0x5c, 0x5c5c), 0x5c5c5f2a, 0x5c5f), 0x5c5c252a, 0x5c25), 0x5c5c5c5c2a, 0x5c5c), 0x5b, 0x5c5b) escape 0x5c))))), 0, iif([__atk4_reuse_left__] is not null and [__atk4_reuse_right__] is not null, 1, null)) from (select sum("a") [__atk4_reuse_left__], sum("b") [__atk4_reuse_right__]) [__atk4_reuse_tmp__]) = 1
                EOF,
            (new MssqlQuery('[where]'))->where($this->e('sum({})', ['a']), 'like', $this->e('sum({})', ['b']))->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where "name" like regexp_replace(:xxaaaa, '(\\[\\_%])|(\\)', '\1\2\2') escape chr(92)
                EOF,
            (new OracleQuery('[where]'))->where('name', 'like', 'foo')->render()[0]
        );

        // regexp | not regexp
        self::assertSame(
            'where regexp_like("name", :a, \'is\')',
            $this->q('[where]')->where('name', 'regexp', 'foo')->render()[0]
        );
        self::assertSame(
            'where not regexp_like("name", :a, \'is\')',
            $this->q('[where]')->where('name', 'not regexp', 'foo')->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where regexp_like(`name`, :a, case when (select __atk4_case_v__ = 'a' from (select `name` __atk4_case_v__ where 0 union all select 'A') __atk4_case_tmp__) then 'is' else '-us' end)
                EOF,
            (new SqliteQuery('[where]'))->where('name', 'regexp', 'foo')->render()[0]
        );
        self::assertSame(
            version_compare(SqliteConnection::getDriverVersion(), '3.45') < 0
                ? <<<'EOF'
                    where regexp_like(sum("a"), sum("b"), case when (select __atk4_case_v__ = 'a' from (select sum("a") __atk4_case_v__ where 0 union all select 'A') __atk4_case_tmp__) then 'is' else '-us' end)
                    EOF
                : <<<'EOF'
                    where (select regexp_like(`__atk4_reuse_left__`, sum("b"), case when (select __atk4_case_v__ = 'a' from (select `__atk4_reuse_left__` __atk4_case_v__ where 0 union all select 'A') __atk4_case_tmp__) then 'is' else '-us' end) from (select sum("a") `__atk4_reuse_left__`) `__atk4_reuse_tmp__`)
                    EOF,
            (new SqliteQuery('[where]'))->where($this->e('sum({})', ['a']), 'regexp', $this->e('sum({})', ['b']))->render()[0]
        );
        foreach (['5.7.0', '8.0.0', 'MariaDB-11.0.0'] as $serverVersion) {
            self::assertSame(
                $serverVersion === '5.7.0'
                    ? 'where `name` regexp concat(\'@?\', :a)'
                    : 'where `name` regexp concat(\'(?s)\', :a)',
                $this->createMysqlQuery($serverVersion, '[where]')->where('name', 'regexp', 'foo')->render()[0]
            );
        }
        self::assertSame(
            <<<'EOF'
                where case when pg_typeof("name") = 'bytea'::regtype then replace(regexp_replace(encode(case when pg_typeof("name") = 'bytea'::regtype then decode(case when pg_typeof("name") = 'bytea'::regtype then replace(substring(cast("name" as text) from 3), chr(92), repeat(chr(92), 2)) else '' end, 'hex') else cast(replace(cast("name" as text), chr(92), repeat(chr(92), 2)) as bytea) end, 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)) ~ replace(regexp_replace(encode(cast(replace(cast(:a as text), chr(92), repeat(chr(92), 2)) as bytea), 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)) else cast("name" as citext) ~ :a end
                EOF,
            (new PostgresqlQuery('[where]'))->where('name', 'regexp', 'foo')->render()[0]
        );
        self::assertSame(
            <<<'EOF'
                where (select case when pg_typeof("__atk4_reuse_left__") = 'bytea'::regtype then replace(regexp_replace(encode(case when pg_typeof("__atk4_reuse_left__") = 'bytea'::regtype then decode(case when pg_typeof("__atk4_reuse_left__") = 'bytea'::regtype then replace(substring(cast("__atk4_reuse_left__" as text) from 3), chr(92), repeat(chr(92), 2)) else '' end, 'hex') else cast(replace(cast("__atk4_reuse_left__" as text), chr(92), repeat(chr(92), 2)) as bytea) end, 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)) ~ replace(regexp_replace(encode(cast(replace(cast("__atk4_reuse_right__" as text), chr(92), repeat(chr(92), 2)) as bytea), 'escape'), '(?<!\\)((\\\\)*)\\(\d\d\d)', '\1©\3©', 'g'), repeat(chr(92), 2), chr(92)) else cast("__atk4_reuse_left__" as citext) ~ "__atk4_reuse_right__" end from (select sum("a") "__atk4_reuse_left__", sum("b") "__atk4_reuse_right__") "__atk4_reuse_tmp__")
                EOF,
            (new PostgresqlQuery('[where]'))->where($this->e('sum({})', ['a']), 'regexp', $this->e('sum({})', ['b']))->render()[0]
        );
        // TODO test MssqlQuery here once REGEXP is supported https://devblogs.microsoft.com/azure-sql/introducing-regular-expression-regex-support-in-azure-sql-db/
        self::assertSame(
            'where regexp_like("name", :xxaaaa, \'in\')',
            (new OracleQuery('[where]'))->where('name', 'regexp', 'foo')->render()[0]
        );
    }

    public function testWhereInWithNullException(): void
    {
        $q = $this->q('[where]')->where('x', 'in', ['a', null, 'b']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Null value in IN operator is not supported');
        $q->render();
    }

    /**
     * Having basically is the same as where, so we can relax and thoroughly test where() instead.
     */
    public function testBasicHaving(): void
    {
        self::assertSame(
            'having "id" = :a',
            $this->q('[having]')->having('id', 1)->render()[0]
        );
        self::assertSame(
            'having "id" > :a',
            $this->q('[having]')->having('id', '>', 1)->render()[0]
        );
        self::assertSame(
            'where "id" = :a having "id" > :b',
            $this->q('[where][having]')->where('id', 1)->having('id', '>', 1)->render()[0]
        );
    }

    public function testLimit(): void
    {
        self::assertSame(
            'limit 0, 100',
            $this->q('[limit]')->limit(100)->render()[0]
        );
        self::assertSame(
            'limit 200, 100',
            $this->q('[limit]')->limit(100, 200)->render()[0]
        );
    }

    public function testOrder(): void
    {
        self::assertSame(
            'order by "name"',
            $this->q('[order]')->order('name')->render()[0]
        );
        self::assertSame(
            'order by "name", "surname"',
            $this->q('[order]')->order('surname')->order('name')->render()[0]
        );
        self::assertSame(
            'order by "name" desc, "surname" desc',
            $this->q('[order]')->order('surname desc')->order('name desc')->render()[0]
        );
        self::assertSame(
            'order by "name" desc, "surname"',
            $this->q('[order]')->order(['name desc', 'surname'])->render()[0]
        );
        self::assertSame(
            'order by "name" desc, "surname"',
            $this->q('[order]')->order('surname')->order('name desc')->render()[0]
        );
        self::assertSame(
            'order by "name" desc, "surname"',
            $this->q('[order]')->order('surname', false)->order('name', true)->render()[0]
        );
        // table name|alias included
        self::assertSame(
            'order by "users"."name"',
            $this->q('[order]')->order('users.name')->render()[0]
        );
        // strange field names
        self::assertSame(
            'order by "my name" desc',
            $this->q('[order]')->order('"my name" desc')->render()[0]
        );
        self::assertSame(
            'order by "жук"',
            $this->q('[order]')->order('жук asc')->render()[0]
        );
        self::assertSame(
            'order by "this is 💩"',
            $this->q('[order]')->order('this is 💩')->render()[0]
        );
        self::assertSame(
            'order by "this is жук" desc',
            $this->q('[order]')->order('this is жук desc')->render()[0]
        );
        self::assertSame(
            'order by * desc',
            $this->q('[order]')->order(['* desc'])->render()[0]
        );
        self::assertSame(
            'order by "{}" desc',
            $this->q('[order]')->order(['{} desc'])->render()[0]
        );
        self::assertSame(
            'order by "* desc"',
            $this->q('[order]')->order($this->e('"* desc"'))->render()[0]
        );
        self::assertSame(
            'order by "* desc"',
            $this->q('[order]')->order($this->q()->expr('{}', ['* desc']))->render()[0]
        );
        self::assertSame(
            'order by "* desc {}"',
            $this->q('[order]')->order($this->q()->expr('{}', ['* desc {}']))->render()[0]
        );
        // custom sort order
        self::assertSame(
            'order by "name" desc nulls last',
            $this->q('[order]')->order('name', 'desc nulls last')->render()[0]
        );
        self::assertSame(
            'order by "name" nulls last',
            $this->q('[order]')->order('name', 'nulls last')->render()[0]
        );
    }

    public function testOrderException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('If first argument is array, second argument must not be used');
        $this->q('[order]')->order(['name', 'surname'], 'desc'); // @phpstan-ignore-line
    }

    public function testGroup(): void
    {
        self::assertSame(
            'group by "gender"',
            $this->q('[group]')->group('gender')->render()[0]
        );
        self::assertSame(
            'group by "gender", "age"',
            $this->q('[group]')->group('gender')->group('age')->render()[0]
        );
        // table name|alias included
        self::assertSame(
            'group by "users"."gender"',
            $this->q('[group]')->group('users.gender')->render()[0]
        );
        // strange field names
        self::assertSame(
            'group by "my name"',
            $this->q('[group]')->group('"my name"')->render()[0]
        );
        self::assertSame(
            'group by "жук"',
            $this->q('[group]')->group('жук')->render()[0]
        );
        self::assertSame(
            'group by "this is 💩"',
            $this->q('[group]')->group('this is 💩')->render()[0]
        );
        self::assertSame(
            'group by "this is жук"',
            $this->q('[group]')->group('this is жук')->render()[0]
        );
        self::assertSame(
            'group by date_format(dat, "%Y")',
            $this->q('[group]')->group($this->e('date_format(dat, "%Y")'))->render()[0]
        );
        self::assertSame(
            'group by date_format(dat, "%Y")',
            $this->q('[group]')->group('date_format(dat, "%Y")')->render()[0]
        );
    }

    public function testGroupConcatException(): void
    {
        // doesn't support groupConcat by default
        $this->expectException(Exception::class);
        $this->q()->groupConcat('foo');
    }

    public function testJoin(): void
    {
        self::assertSame(
            'left join "address" on "address"."id" = "address_id"',
            $this->q('[join]')->join('address')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."id" = "address_id"',
            $this->q('[join]')->join('address a')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."id" = "user"."address_id"',
            $this->q('[join]')->table('user')->join('address a')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."id" = "user"."my_address_id"',
            $this->q('[join]')->table('user')->join('address a', 'my_address_id')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."id" = "u"."address_id"',
            $this->q('[join]')->table('user', 'u')->join('address a')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."user_id" = "u"."id"',
            $this->q('[join]')->table('user', 'u')->join('address.user_id a')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."user_id" = "u"."id" '
            . 'left join "bank" "b" on "b"."id" = "u"."bank_id"',
            $this->q('[join]')->table('user', 'u')
                ->join('address.user_id', null, null, 'a')->join('bank', null, null, 'b')
                ->render()[0]
        );
        self::assertSame(
            'left join "address" on "address"."user_id" = "u"."id" '
            . 'left join "bank" on "bank"."id" = "u"."bank_id"',
            $this->q('[join]')->table('user', 'u')
                ->join('address.user_id')->join('bank')->render()[0]
        );
        self::assertSame(
            'left join "address" "a" on "a"."user_id" = "u"."id" '
            . 'left join "bank" "b" on "b"."id" = "u"."bank_id" '
            . 'left join "bank_details" on "bank_details"."id" = "bank"."details_id"',
            $this->q('[join]')->table('user', 'u')
                ->join('address.user_id', null, null, 'a')->join('bank', null, null, 'b')
                ->join('bank_details', 'bank.details_id')->render()[0]
        );

        self::assertSame(
            'left join "address" "a" on a.name like u.pattern',
            $this->q('[join]')->table('user', 'u')
                ->join('address a', $this->e('a.name like u.pattern'))->render()[0]
        );
    }

    /**
     * Combined execution of where() clauses.
     */
    public function testCombinedWhere(): void
    {
        self::assertSame(
            'select "name" from "employee" where "a" = :a',
            $this->q()
                ->field('name')->table('employee')->where('a', 1)
                ->render()[0]
        );

        self::assertSame(
            'select "name" from "employee" where "employee"."a" = :a',
            $this->q()
                ->field('name')->table('employee')->where('employee.a', 1)
                ->render()[0]
        );

        self::assertSame(
            'select "name" from "db"."employee" where "db"."employee"."a" = :a',
            $this->q()
                ->field('name')->table('db.employee')->where('db.employee.a', 1)
                ->render()[0]
        );

        self::assertSame(
            'delete from "employee" where "employee"."a" = :a',
            $this->q()
                ->mode('delete')
                ->table('employee')->where('employee.a', 1)
                ->render()[0]
        );

        $userIds = $this->q()->table('expired_users')->field('user_id');

        self::assertSame(
            'update "user" set "active"=:a  where "id" in (select "user_id" from "expired_users")',
            $this->q()
                ->table('user')
                ->where('id', 'in', $userIds)
                ->set('active', 0)
                ->mode('update')
                ->render()[0]
        );
    }

    public function testEmptyOrAndWhere(): void
    {
        // empty condition equals to no condition
        self::assertSame(
            '',
            $this->q()->orExpr()->render()[0]
        );

        self::assertSame(
            '',
            $this->q()->andExpr()->render()[0]
        );
    }

    public function testInsertDeleteUpdate(): void
    {
        // delete template
        self::assertSame(
            'delete from "employee" where "name" = :a',
            $this->q()
                ->table('employee')->where('name', 1)
                ->mode('delete')
                ->render()[0]
        );

        // update template
        self::assertSame(
            'update "employee" set "name"=:a',
            $this->q()
                ->table('employee')->set('name', 1)
                ->mode('update')
                ->render()[0]
        );

        self::assertSame(
            'update "employee" set "name"="name"+1',
            $this->q()
                ->table('employee')->set('name', $this->e('"name"+1'))
                ->mode('update')
                ->render()[0]
        );

        // insert template
        self::assertSame(
            'insert into "employee" ("name") values (:a)',
            $this->q()
                ->table('employee')->set('name', 1)
                ->mode('insert')
                ->render()[0]
        );

        // set multiple fields
        self::assertSame(
            'insert into "employee" ("time", "name") values (now(), :a)',
            $this->q()
                ->table('employee')
                ->set('time', $this->e('now()'))
                ->set('name', 'unknown')
                ->mode('insert')
                ->render()[0]
        );

        // set as array
        self::assertSame(
            'insert into "employee" ("time", "name") values (now(), :a)',
            $this->q()
                ->table('employee')
                ->setMulti(['time' => $this->e('now()'), 'name' => 'unknown'])
                ->mode('insert')
                ->render()[0]
        );
    }

    public function testMiscInsert(): void
    {
        $data = [
            'id' => null,
            'system_id' => '3576',
            'system' => null,
            'created_dts' => 123,
            'contractor_from' => null,
            'contractor_to' => null,
            'vat_rate_id' => null,
            'currency_id' => null,
            'vat_period_id' => null,
            'journal_spec_id' => '147735',
            'job_id' => '9341',
            'nominal_id' => null,
            'root_nominal_code' => null,
            'doc_type' => null,
            'is_cn' => 'N',
            'doc_date' => null,
            'ref_no' => '940 testingqq11111',
            'po_ref' => null,
            'total_gross' => '100.00',
            'total_net' => null,
            'total_vat' => null,
            'exchange_rate' => 1.892134,
            'note' => null,
            'archive' => 'N',
            'fx_document_id' => null,
            'exchanged_total_net' => null,
            'exchanged_total_gross' => null,
            'exchanged_total_vat' => null,
            'exchanged_total_a' => null,
            'exchanged_total_b' => null,
        ];

        $q = $this->q();
        $q->mode('insert');
        foreach ($data as $k => $v) {
            $q->set($k, $v);
        }

        self::assertSame(
            'insert into  ("' . implode('", "', array_keys($data)) . '") values (:a, :b, :c, :d, :e, :f, :g, :h, :i, :j, :k, :l, :m, :n, :o, :p, :q, :r, :s, :t, :u, :v, :w, :x, :y, :z, :aa, :ab, :ac, :ad)',
            $q->render()[0]
        );
    }

    public function testSetReturnThis(): void
    {
        $q = $this->q();
        self::assertSame($q, $q->set('id', 1));
    }

    /**
     * Value of type array is not supported by SQL.
     */
    public function testSetException1(): void
    {
        $this->expectException(Exception::class);
        $this->q()->set('name', []);
    }

    /**
     * Field name can be expression.
     *
     * @doesNotPerformAssertions
     */
    #[DoesNotPerformAssertions]
    public function testSetException2(): void
    {
        $this->q()->set($this->e('foo'), 1);
    }

    public function testNestedOrAnd(): void
    {
        // test 1
        $q = $this->q();
        $q->table('employee')->field('name');
        $q->where(
            $q
                ->orExpr()
                ->where('a', 1)
                ->where('b', 1)
        );
        self::assertSame(
            'select "name" from "employee" where ("a" = :a or "b" = :b)',
            $q->render()[0]
        );

        // test 2
        $q = $this->q();
        $q->table('employee')->field('name');
        $q->where(
            $q
                ->orExpr()
                ->where('a', 1)
                ->where('b', 1)
                ->where(
                    $q->andExpr()
                        ->where('1 = 1')
                        ->where('1 = 0')
                )
        );
        self::assertSame(
            'select "name" from "employee" where ("a" = :a or "b" = :b or ((1 = 1) and (1 = 0)))',
            $q->render()[0]
        );
    }

    public function testNestedOrAndHaving(): void
    {
        $q = $this->q();
        $q->table('employee')->field($this->e('sum({})', ['amount']), 'salary')->group('type');
        $q->having(
            $q
                ->orExpr()
                ->having('a', 1)
                ->having('b', 1)
        );
        self::assertSame(
            'select sum("amount") "salary" from "employee" group by "type" having ("a" = :a or "b" = :b)',
            $q->render()[0]
        );
    }

    public function testNestedOrAndHavingWithWhereException(): void
    {
        $q = $this->q();
        $q->table('employee')->field($this->e('sum({})', ['amount']), 'salary')->group('type');
        $q->having(
            $q
                ->orExpr()
                ->where('a', 1)
                ->having('b', 1) // mixing triggers Exception on render
        );

        $this->expectException(Exception::class);
        $q->render();
    }

    public function testReset(): void
    {
        // reset everything
        $q = $this->q()->table('user')->where('name', 'John');
        $q->reset();
        self::assertSame('select *', $q->render()[0]);

        // reset particular tag
        $q = $this->q()
            ->table('user')
            ->where('name', 'John')
            ->reset('where')
            ->where('surname', 'Doe');
        self::assertSame('select * from "user" where "surname" = :a', $q->render()[0]);
    }

    public function testOption(): void
    {
        // single option
        self::assertSame(
            'select calc_found_rows * from "test"',
            $this->q()->table('test')->option('calc_found_rows')->render()[0]
        );
        // multiple options
        self::assertSame(
            'select calc_found_rows ignore * from "test"',
            $this->q()->table('test')->option('calc_found_rows')->option('ignore')->render()[0]
        );
        // options for specific modes
        $q = $this->q()
            ->table('test')
            ->field('name')
            ->set('name', 1)
            ->option('calc_found_rows', 'select') // for default select mode
            ->option('ignore', 'insert'); // for insert mode

        self::assertSame(
            'select calc_found_rows "name" from "test"',
            $q->mode('select')->render()[0]
        );
        self::assertSame(
            'insert ignore into "test" ("name") values (:a)',
            $q->mode('insert')->render()[0]
        );
        self::assertSame(
            'update "test" set "name"=:a',
            $q->mode('update')->render()[0]
        );
    }

    public function testCaseExprNormal(): void
    {
        $s = $this->q()->caseExpr()
            ->caseWhen(['status', 'New'], 't2.expose_new')
            ->caseWhen(['status', 'like', '%Used%'], 't2.expose_used')
            ->caseElse(null)
            ->render()[0];
        self::assertSame(<<<'EOF'
            case when "status" = :a then :b when "status" like regexp_replace(:c, '(\\[\\_%])|(\\)', '\1\2\2') escape '\' then :d else :e end
            EOF, $s);

        // with subqueries
        $age = $this->e('year(now()) - year(birth_date)');
        $q = $this->q()->table('user')->field($age, 'calc_age');

        $s = $this->q()->caseExpr()
            ->caseWhen(['age', '>', $q], 'Older')
            ->caseElse('Younger')
            ->render()[0];
        self::assertSame('case when "age" > (select year(now()) - year(birth_date) "calc_age" from "user") then :a else :b end', $s);
    }

    public function testCaseExprShortForm(): void
    {
        $s = $this->q()->caseExpr('status')
            ->caseWhen('New', 't2.expose_new')
            ->caseWhen('Used', 't2.expose_used')
            ->caseElse(null)
            ->render()[0];
        self::assertSame('case "status" when :a then :b when :c then :d else :e end', $s);

        // with subqueries
        $age = $this->e('year(now()) - year(birth_date)');
        $q = $this->q()->table('user')->field($age, 'calc_age');

        $s = $this->q()->caseExpr($q)
            ->caseWhen(100, 'Very old')
            ->caseElse('Younger')
            ->render()[0];
        self::assertSame('case (select year(now()) - year(birth_date) "calc_age" from "user") when :a then :b else :c end', $s);
    }

    /**
     * Incorrect use of "when" method parameters.
     *
     * @doesNotPerformAssertions
     */
    #[DoesNotPerformAssertions]
    public function testCaseExprException1(): void
    {
        // $this->expectException(Exception::class);
        $this->q()->caseExpr()
            ->caseWhen(['status'], 't2.expose_new')
            ->render();
    }

    /**
     * When using short form CASE statement, then you should not set array as when() method 1st parameter.
     */
    public function testCaseExprException2(): void
    {
        $this->expectException(Exception::class);
        $this->q()->caseExpr('status')
            ->caseWhen(['status', 'New'], 't2.expose_new')
            ->render();
    }

    public function testExprNow(): void
    {
        self::assertSame(
            'update "employee" set "hired"=current_timestamp()',
            $this->q()
                ->table('employee')->set('hired', $this->q()->exprNow())
                ->mode('update')
                ->render()[0]
        );

        self::assertSame(
            'update "employee" set "hired"=current_timestamp(:a)',
            $this->q()
                ->table('employee')->set('hired', $this->q()->exprNow(2))
                ->mode('update')
                ->render()[0]
        );
    }

    public function testTableNameWithDot(): void
    {
        // render table
        self::assertSame(
            '"foo"."bar"',
            $this->callProtected($this->q()->table('foo.bar'), '_renderTable')
        );

        self::assertSame(
            '"foo"."bar" "a"',
            $this->callProtected($this->q()->table('foo.bar', 'a'), '_renderTable')
        );

        // where clause
        self::assertSame(
            'select "name" from "db1"."employee" where "a" = :a',
            $this->q()
                ->field('name')->table('db1.employee')->where('a', 1)
                ->render()[0]
        );

        self::assertSame(
            'select "name" from "db1"."employee" where "db1"."employee"."a" = :a',
            $this->q()
                ->field('name')->table('db1.employee')->where('db1.employee.a', 1)
                ->render()[0]
        );
    }

    public function testWith(): void
    {
        $q1 = $this->q()->table('salaries')->field('salary');

        $q2 = $this->q()
            ->with($q1, 'q1')
            ->table('q1');
        self::assertSame('with "q1" as (select "salary" from "salaries")' . "\n"
            . 'select * from "q1"', $q2->render()[0]);

        $q2 = $this->q()
            ->with($q1, 'q1', null, true)
            ->table('q1');
        self::assertSame('with recursive "q1" as (select "salary" from "salaries")' . "\n"
            . 'select * from "q1"', $q2->render()[0]);

        $q2 = $this->q()
            ->with($q1, 'q11', ['foo', 'qwe"ry'])
            ->with($q1, 'q12', ['bar', 'baz'], true) // this one is recursive
            ->table('q11')
            ->table('q12');
        self::assertSame('with recursive "q11" ("foo", "qwe""ry") as (select "salary" from "salaries"),' . "\n"
            . '"q12" ("bar", "baz") as (select "salary" from "salaries")' . "\n" . 'select * from "q11", "q12"', $q2->render()[0]);

        // now test some more useful real life query
        $quotes = $this->q()
            ->table('quotes')
            ->field('emp_id')
            ->field($this->q()->expr('sum({})', ['total_net']))
            ->group('emp_id');
        $invoices = $this->q()
            ->table('invoices')
            ->field('emp_id')
            ->field($this->q()->expr('sum({})', ['total_net']))
            ->group('emp_id');
        $q = $this->q()
            ->with($quotes, 'q', ['emp', 'quoted'])
            ->with($invoices, 'i', ['emp', 'invoiced'])
            ->table('employees')
            ->join('q.emp')
            ->join('i.emp')
            ->field('name')
            ->field('salary')
            ->field('q.quoted')
            ->field('i.invoiced');
        self::assertSame(
            'with '
                . '"q" ("emp", "quoted") as (select "emp_id", sum("total_net") from "quotes" group by "emp_id"),' . "\n"
                . '"i" ("emp", "invoiced") as (select "emp_id", sum("total_net") from "invoices" group by "emp_id")' . "\n"
            . 'select "name", "salary", "q"."quoted", "i"."invoiced" '
            . 'from "employees" '
                . 'left join "q" on "q"."emp" = "employees"."id" '
                . 'left join "i" on "i"."emp" = "employees"."id"',
            $q->render()[0]
        );
    }
}
