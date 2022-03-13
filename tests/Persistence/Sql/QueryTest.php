<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Mssql;
use Atk4\Data\Persistence\Sql\Mysql;
use Atk4\Data\Persistence\Sql\Oracle;
use Atk4\Data\Persistence\Sql\Postgresql;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Persistence\Sql\Sqlite;

/**
 * @coversDefaultClass \Atk4\Data\Persistence\Sql\Query
 */
class QueryTest extends TestCase
{
    /**
     * @param string|array ...$args
     */
    public function q(...$args): Query
    {
        return new Query(...$args);
    }

    /**
     * @covers ::__construct
     */
    public function testConstruct(): void
    {
        // passing properties in constructor
        $this->assertSame(
            '"q"',
            $this->callProtected($this->q(), 'escapeIdentifier', 'q')
        );
    }

    /**
     * dsql() should return new Query object and inherit connection from it.
     *
     * @covers ::dsql
     */
    public function testDsql(): void
    {
        $q = $this->q(['connection' => new Mysql\Connection()]);
        $this->assertInstanceOf(Mysql\Connection::class, $q->dsql()->connection);
    }

    /**
     * field() should return $this Query for chaining.
     *
     * @covers ::field
     */
    public function testFieldReturnValue(): void
    {
        $q = $this->q();
        $this->assertSame($q, $q->field('first_name'));
    }

    /**
     * Testing field - basic cases.
     *
     * @covers ::_render_field
     * @covers ::field
     */
    public function testFieldBasic(): void
    {
        $this->assertSame(
            '"first_name"',
            $this->callProtected($this->q()->field('first_name'), '_render_field')
        );
        $this->assertSame(
            '"first_name", "last_name"',
            $this->callProtected($this->q()->field('first_name')->field('last_name'), '_render_field')
        );
        $this->assertSame(
            '"last_name"',
            $this->callProtected($this->q()->field('first_name')->reset('field')->field('last_name'), '_render_field')
        );
        $this->assertSame(
            '*',
            $this->callProtected($this->q()->field('first_name')->reset('field'), '_render_field')
        );
        $this->assertSame(
            '*',
            $this->callProtected($this->q()->field('first_name')->reset(), '_render_field')
        );
        $this->assertSame(
            '"employee"."first_name"',
            $this->callProtected($this->q()->field('employee.first_name'), '_render_field')
        );
        $this->assertSame(
            '"first_name" "name"',
            $this->callProtected($this->q()->field('first_name', 'name'), '_render_field')
        );
        $this->assertSame(
            '*',
            $this->callProtected($this->q()->field('*'), '_render_field')
        );
        $this->assertSame(
            '"employee"."first_name"',
            $this->callProtected($this->q()->field('employee.first_name'), '_render_field')
        );
    }

    /**
     * Testing field - defaultField.
     *
     * @covers ::_render_field
     * @covers ::field
     */
    public function testFieldDefaultField(): void
    {
        // default defaultField
        $this->assertSame(
            '*',
            $this->callProtected($this->q(), '_render_field')
        );
        // defaultField as custom string - not escaped
        $this->assertSame(
            'id',
            $this->callProtected($this->q(['defaultField' => 'id']), '_render_field')
        );
        // defaultField as custom string with dot - not escaped
        $this->assertSame(
            'all.values',
            $this->callProtected($this->q(['defaultField' => 'all.values']), '_render_field')
        );
        // defaultField as Expression object - not escaped
        $this->assertSame(
            'values()',
            $this->callProtected($this->q(['defaultField' => new Expression('values()')]), '_render_field')
        );
    }

    /**
     * Testing field - basic cases.
     *
     * @covers ::_render_field
     * @covers ::field
     */
    public function testFieldExpression(): void
    {
        $this->assertSame(
            '"name"',
            $this->q('[field]')->field('name')->render()[0]
        );
        $this->assertSame(
            '"first name"',
            $this->q('[field]')->field('first name')->render()[0]
        );
        $this->assertSame(
            '"first"."name"',
            $this->q('[field]')->field('first.name')->render()[0]
        );
        $this->assertSame(
            'now()',
            $this->q('[field]')->field('now()')->render()[0]
        );
        $this->assertSame(
            'now()',
            $this->q('[field]')->field(new Expression('now()'))->render()[0]
        );
        // Usage of field aliases
        $this->assertSame(
            'now() "time"',
            $this->q('[field]')->field('now()', 'time')->render()[0]
        );
        $this->assertSame(// alias can be passed as 2nd argument
            'now() "time"',
            $this->q('[field]')->field(new Expression('now()'), 'time')->render()[0]
        );
    }

    /**
     * Duplicate alias of field.
     *
     * @covers ::_set_args
     * @covers ::field
     */
    public function testFieldException1(): void
    {
        $this->expectException(Exception::class);
        $this->q()->field('name', 'a')->field('surname', 'a');
    }

    /**
     * Alias is NOT mandatory when pass table as Expression.
     *
     * @covers ::table
     *
     * @doesNotPerformAssertions
     */
    public function testTableException3(): void
    {
        // $this->expectException(Exception::class); // no more
        $this->q()->table($this->q()->expr('test'));
    }

    /**
     * Alias is IS mandatory when pass table as Query.
     *
     * @covers ::table
     */
    public function testTableException4(): void
    {
        $this->expectException(Exception::class);
        $this->q()->table($this->q()->table('test'));
    }

    /**
     * Table aliases should be unique.
     *
     * @covers ::_set_args
     * @covers ::table
     */
    public function testTableException5(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->table('foo', 'a')
            ->table('bar', 'a');
    }

    /**
     * Table aliases should be unique.
     *
     * @covers ::_set_args
     * @covers ::table
     */
    public function testTableException6(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->table('foo', 'bar')
            ->table('bar');
    }

    /**
     * Table aliases should be unique.
     *
     * @covers ::_set_args
     * @covers ::table
     */
    public function testTableException7(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->table('foo')
            ->table('foo');
    }

    /**
     * Table aliases should be unique.
     *
     * @covers ::_set_args
     * @covers ::table
     */
    public function testTableException8(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->table($this->q()->table('test'), 'foo')
            ->table('foo');
    }

    /**
     * Table aliases should be unique.
     *
     * @covers ::_set_args
     * @covers ::table
     */
    public function testTableException9(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->table('foo')
            ->table($this->q()->table('test'), 'foo');
    }

    /**
     * Table can't be set as sub-Query in Update query mode.
     *
     * @covers ::table
     */
    public function testTableException10(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->mode('update')
            ->table($this->q()->table('test'), 'foo')
            ->field('name')->set('name', 1)
            ->render();
    }

    /**
     * Table can't be set as sub-Query in Insert query mode.
     *
     * @covers ::table
     */
    public function testTableException11(): void
    {
        $this->expectException(Exception::class);
        $this->q()
            ->mode('insert')
            ->table($this->q()->table('test'), 'foo')
            ->field('name')->set('name', 1)
            ->render();
    }

    /**
     * Requesting non-existant query mode should throw exception.
     *
     * @covers ::mode
     */
    public function testModeException1(): void
    {
        $this->expectException(Exception::class);
        $this->q()->mode('non_existant_mode');
    }

    /**
     * table() should return $this Query for chaining.
     *
     * @covers ::table
     */
    public function testTableReturnValue(): void
    {
        $q = $this->q();
        $this->assertSame($q, $q->table('employee'));
    }

    /**
     * @covers ::_render_table
     * @covers ::_render_table_noalias
     * @covers ::table
     */
    public function testTableRender1(): void
    {
        // no table defined
        $this->assertSame(
            'select now()',
            $this->q()
                ->field(new Expression('now()'))
                ->render()[0]
        );

        // one table
        $this->assertSame(
            'select "name" from "employee"',
            $this->q()
                ->field('name')->table('employee')
                ->render()[0]
        );

        $this->assertSame(
            'select "na#me" from "employee"',
            $this->q()
                ->field('"na#me"')->table('employee')
                ->render()[0]
        );
        $this->assertSame(
            'select "na""me" from "employee"',
            $this->q()
                ->field(new Expression('{}', ['na"me']))->table('employee')
                ->render()[0]
        );
        $this->assertSame(
            'select "жук" from "employee"',
            $this->q()
                ->field(new Expression('{}', ['жук']))->table('employee')
                ->render()[0]
        );
        $this->assertSame(
            'select "this is 💩" from "employee"',
            $this->q()
                ->field(new Expression('{}', ['this is 💩']))->table('employee')
                ->render()[0]
        );

        $this->assertSame(
            'select "name" from "employee" "e"',
            $this->q()
                ->field('name')->table('employee', 'e')
                ->render()[0]
        );
        $this->assertSame(
            'select * from "employee" "e"',
            $this->q()
                ->table('employee', 'e')
                ->render()[0]
        );

        // multiple tables
        $this->assertSame(
            'select "employee"."name" from "employee", "jobs"',
            $this->q()
                ->field('employee.name')->table('employee')->table('jobs')
                ->render()[0]
        );

        // multiple tables with aliases
        $this->assertSame(
            'select "name" from "employee", "jobs" "j"',
            $this->q()
                ->field('name')->table('employee')->table('jobs', 'j')
                ->render()[0]
        );
        $this->assertSame(
            'select "name" from "employee" "e", "jobs" "j"',
            $this->q()
                ->field('name')->table('employee', 'e')->table('jobs', 'j')
                ->render()[0]
        );
        // testing _render_table_noalias, shouldn't render table alias 'emp'
        $this->assertSame(
            'insert into "employee" ("name") values (:a)',
            $this->q()
                ->field('name')->table('employee', 'emp')->set('name', 1)
                ->mode('insert')
                ->render()[0]
        );
        $this->assertSame(
            'update "employee" set "name"=:a',
            $this->q()
                ->field('name')->table('employee', 'emp')->set('name', 1)
                ->mode('update')
                ->render()[0]
        );
    }

    /**
     * @covers ::_render_table
     * @covers ::table
     */
    public function testTableRender2(): void
    {
        // pass table as expression or query
        $q = $this->q()->table('employee');

        $this->assertSame(
            'select "name" from (select * from "employee") "e"',
            $this->q()
                ->field('name')->table($q, 'e')
                ->render()[0]
        );

        $this->assertSame(
            'select "name" from "myt""able"',
            $this->q()
                ->field('name')->table(new Expression('{}', ['myt"able']))
                ->render()[0]
        );

        // test with multiple sub-queries as tables
        $q1 = $this->q()->table('employee');
        $q2 = $this->q()->table('customer');

        $this->assertSame(
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

    /**
     * @covers ::render
     * @covers \Atk4\Data\Persistence\Sql\Expression::consume
     * @covers \Atk4\Data\Persistence\Sql\Expression::render
     */
    public function testBasicRenderSubquery(): void
    {
        $age = new Expression('coalesce([age], [default_age])');
        $age['age'] = new Expression('year(now()) - year(birth_date)');
        $age['default_age'] = 18;

        $q = $this->q()->table('user')->field($age, 'calculated_age');

        $this->assertSame(
            'select coalesce(year(now()) - year(birth_date), :a) "calculated_age" from "user"',
            $q->render()[0]
        );
    }

    /**
     * @covers \Atk4\Data\Persistence\Sql\Expression::getDebugQuery
     */
    public function testGetDebugQuery(): void
    {
        $age = new Expression('coalesce([age], [default_age], [foo], [bar])');
        $age['age'] = new Expression('year(now()) - year(birth_date)');
        $age['default_age'] = 18;
        $age['foo'] = 'foo';
        $age['bar'] = null;

        $q = $this->q()->table('user')->field($age, 'calculated_age');

        $this->assertSame(
            preg_replace('~\s+~', '', 'select coalesce(year(now()) - year(birth_date), 18, \'foo\', NULL) "calculated_age" from "user"'),
            preg_replace('~\s+~', '', $q->getDebugQuery())
        );
    }

    /**
     * @covers ::__debugInfo
     */
    public function testVarDump(): void
    {
        $this->assertMatchesRegularExpression(
            '~select\s+\*\s+from\s*"user"~',
            $this->q()->table('user')->__debugInfo()['R']
        );
    }

    /**
     * @covers ::__debugInfo
     */
    public function testVarDump2(): void
    {
        $this->assertStringContainsString(
            'Expression could not render tag',
            (new Expression('Hello [world]'))->__debugInfo()['R']
        );
    }

    /**
     * @covers ::__debugInfo
     */
    public function testVarDump3(): void
    {
        $this->assertStringContainsString(
            'Hello \'php\'',
            (new Expression('Hello [world]', ['world' => 'php']))->__debugInfo()['R']
        );
    }

    /**
     * @covers ::__debugInfo
     */
    public function testVarDump4(): void
    {
        // should throw exception "Table cannot be Query in UPDATE, INSERT etc. query modes"
        $this->assertStringContainsString(
            'Table cannot be Query',
            ($this->q()
                ->mode('update')
                ->table($this->q()->table('test'), 'foo'))->__debugInfo()['R']
        );
    }

    /**
     * @covers ::_render_field
     * @covers ::_render_table
     * @covers ::field
     * @covers ::render
     * @covers ::table
     */
    public function testUnionQuery(): void
    {
        // 1st query
        $q1 = $this->q()
            ->table('sales')
            ->field('date')
            ->field('amount', 'debit')
            ->field($this->q()->expr('0'), 'credit'); // simply 0
        $this->assertSame(
            'select "date", "amount" "debit", 0 "credit" from "sales"',
            $q1->render()[0]
        );

        // 2nd query
        $q2 = $this->q()
            ->table('purchases')
            ->field('date')
            ->field($this->q()->expr('0'), 'debit') // simply 0
            ->field('amount', 'credit');
        $this->assertSame(
            'select "date", 0 "debit", "amount" "credit" from "purchases"',
            $q2->render()[0]
        );

        // $q1 union $q2
        $u = new Expression('([] union [])', [$q1, $q2]);
        $this->assertSame(
            '((select "date", "amount" "debit", 0 "credit" from "sales") union (select "date", 0 "debit", "amount" "credit" from "purchases"))',
            $u->render()[0]
        );

        // SELECT date, debit, credit FROM ($q1 union $q2)
        $q = $this->q()
            ->field('date')
            ->field('debit')
            ->field('credit')
            ->table($u, 'derrivedTable');
        $this->assertSame(
            'select "date", "debit", "credit" from ((select "date", "amount" "debit", 0 "credit" from "sales") union (select "date", 0 "debit", "amount" "credit" from "purchases")) "derrivedTable"',
            $q->render()[0]
        );

        // SQLite do not support (($q1) union ($q2)) syntax. Correct syntax is ($q1 union $q2) without additional braces
        // Other SQL engines are more relaxed, but still these additional braces are not needed for union
        // Let's test how to do that properly
        $q1->wrapInParentheses = false;
        $q2->wrapInParentheses = false;
        $u = new Expression('([] union [])', [$q1, $q2]);
        $this->assertSame(
            '(select "date", "amount" "debit", 0 "credit" from "sales" union select "date", 0 "debit", "amount" "credit" from "purchases")',
            $u->render()[0]
        );

        // SELECT date, debit, credit FROM ($q1 union $q2)
        $q = $this->q()
            ->field('date')
            ->field('debit')
            ->field('credit')
            ->table($u, 'derrivedTable');
        $this->assertSame(
            'select "date", "debit", "credit" from (select "date", "amount" "debit", 0 "credit" from "sales" union select "date", 0 "debit", "amount" "credit" from "purchases") "derrivedTable"',
            $q->render()[0]
        );
    }

    /**
     * where() should return $this Query for chaining.
     *
     * @covers ::where
     */
    public function testWhereReturnValue(): void
    {
        $q = $this->q();
        $this->assertSame($q, $q->where('id', 1));
    }

    /**
     * having() should return $this Query for chaining.
     *
     * @covers ::field
     */
    public function testHavingReturnValue(): void
    {
        $q = $this->q();
        $this->assertSame($q, $q->having('id', 1));
    }

    /**
     * Basic where() tests.
     *
     * @covers ::_render_where
     * @covers ::_sub_render_where
     * @covers ::where
     */
    public function testWhereBasic(): void
    {
        // one parameter as a string - treat as expression
        $this->assertSame(
            'where (now())',
            $this->q('[where]')->where('now()')->render()[0]
        );
        $this->assertSame(
            'where (foo >=    bar)',
            $this->q('[where]')->where('foo >=    bar')->render()[0]
        );

        // two parameters - field, value
        $this->assertSame(
            'where "id" = :a',
            $this->q('[where]')->where('id', 1)->render()[0]
        );
        $this->assertSame(
            'where "user"."id" = :a',
            $this->q('[where]')->where('user.id', 1)->render()[0]
        );
        $this->assertSame(
            'where "db"."user"."id" = :a',
            $this->q('[where]')->where('db.user.id', 1)->render()[0]
        );
        $this->assertSame(
            'where "id" is null',
            $this->q('[where]')->where('id', null)->render()[0]
        );
        $this->assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', '!=', null)->render()[0]
        );

        // three parameters - field, condition, value
        $this->assertSame(
            'where "id" > :a',
            $this->q('[where]')->where('id', '>', 1)->render()[0]
        );
        $this->assertSame(
            'where "id" < :a',
            $this->q('[where]')->where('id', '<', 1)->render()[0]
        );
        $this->assertSame(
            'where "id" = :a',
            $this->q('[where]')->where('id', '=', 1)->render()[0]
        );
        $this->assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', '=', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" in (select * from "user")',
            $this->q('[where]')->where('id', $this->q()->table('user'))->render()[0]
        );

        // field name with special symbols - not escape
        $this->assertSame(
            'where now() = :a',
            $this->q('[where]')->where('now()', 1)->render()[0]
        );

        // field name as expression
        $this->assertSame(
            'where now = :a',
            $this->q('[where]')->where(new Expression('now'), 1)->render()[0]
        );

        // more than one where condition - join with AND keyword
        $this->assertSame(
            'where "a" = :a and "b" is null',
            $this->q('[where]')->where('a', 1)->where('b', null)->render()[0]
        );
    }

    public function testWhereExpression(): void
    {
        $this->assertSame(
            'where (a = 5 or b = 6) and (c = 3 or d = 1)',
            $this->q('[where]')->where('a = 5 or b = 6')->where('c = 3 or d = 1')->render()[0]
        );
    }

    public function testWhereIncompatibleFieldWithCondition(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('id=', 1)->render();
    }

    /**
     * Verify that passing garbage to where throw exception.
     *
     * @covers ::order
     */
    public function testWhereIncompatibleObject1(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('a', new \DateTime())->render();
    }

    /**
     * Verify that passing garbage to where throw exception.
     *
     * @covers ::order
     */
    public function testWhereIncompatibleObject2(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('a', new \DateTime());
    }

    /**
     * Verify that passing garbage to where throw exception.
     *
     * @covers ::order
     */
    public function testWhereIncompatibleObject3(): void
    {
        $this->expectException(Exception::class);
        $this->q('[where]')->where('a', '<>', new \DateTime());
    }

    /**
     * Testing where() with special values - null, array, like.
     *
     * @covers ::_render_where
     * @covers ::_sub_render_where
     * @covers ::where
     */
    public function testWhereSpecialValues(): void
    {
        // in | not in
        $this->assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', 'in', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', 'not in', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', 'not', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', '=', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', '<>', [1, 2])->render()[0]
        );
        $this->assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', '!=', [1, 2])->render()[0]
        );
        // speacial treatment for empty array values
        $this->assertSame(
            'where 1 = 0',
            $this->q('[where]')->where('id', '=', [])->render()[0]
        );
        $this->assertSame(
            'where 1 = 1',
            $this->q('[where]')->where('id', '<>', [])->render()[0]
        );
        // pass array as CSV
        $this->assertSame(
            'where "id" in (:a, :b)',
            $this->q('[where]')->where('id', 'in', '1, 2')->render()[0]
        );
        $this->assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', 'not in', '1,    2')->render()[0]
        );
        $this->assertSame(
            'where "id" not in (:a, :b)',
            $this->q('[where]')->where('id', 'not', '1,2')->render()[0]
        );

        // is | is not
        $this->assertSame(
            'where "id" is null',
            $this->q('[where]')->where('id', 'is', null)->render()[0]
        );
        $this->assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', 'is not', null)->render()[0]
        );
        $this->assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', 'not', null)->render()[0]
        );
        $this->assertSame(
            'where "id" is null',
            $this->q('[where]')->where('id', '=', null)->render()[0]
        );
        $this->assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', '<>', null)->render()[0]
        );
        $this->assertSame(
            'where "id" is not null',
            $this->q('[where]')->where('id', '!=', null)->render()[0]
        );

        // like | not like
        $this->assertSame(
            'where "name" like :a',
            $this->q('[where]')->where('name', 'like', 'foo')->render()[0]
        );
        $this->assertSame(
            'where "name" not like :a',
            $this->q('[where]')->where('name', 'not like', 'foo')->render()[0]
        );
    }

    /**
     * Having basically is the same as where, so we can relax and trouhly test where() instead.
     *
     * @covers ::_render_having
     * @covers ::having
     */
    public function testBasicHaving(): void
    {
        $this->assertSame(
            'having "id" = :a',
            $this->q('[having]')->having('id', 1)->render()[0]
        );
        $this->assertSame(
            'having "id" > :a',
            $this->q('[having]')->having('id', '>', 1)->render()[0]
        );
        $this->assertSame(
            'where "id" = :a having "id" > :b',
            $this->q('[where][having]')->where('id', 1)->having('id', '>', 1)->render()[0]
        );
    }

    /**
     * Test Limit.
     *
     * @covers ::_render_limit
     * @covers ::limit
     */
    public function testLimit(): void
    {
        $this->assertSame(
            'limit 0, 100',
            $this->q('[limit]')->limit(100)->render()[0]
        );
        $this->assertSame(
            'limit 200, 100',
            $this->q('[limit]')->limit(100, 200)->render()[0]
        );
    }

    /**
     * Test Order.
     *
     * @covers ::_render_order
     * @covers ::order
     */
    public function testOrder(): void
    {
        $this->assertSame(
            'order by "name"',
            $this->q('[order]')->order('name')->render()[0]
        );
        $this->assertSame(
            'order by "name", "surname"',
            $this->q('[order]')->order('surname')->order('name')->render()[0]
        );
        $this->assertSame(
            'order by "name" desc, "surname" desc',
            $this->q('[order]')->order('surname desc')->order('name desc')->render()[0]
        );
        $this->assertSame(
            'order by "name" desc, "surname"',
            $this->q('[order]')->order(['name desc', 'surname'])->render()[0]
        );
        $this->assertSame(
            'order by "name" desc, "surname"',
            $this->q('[order]')->order('surname')->order('name desc')->render()[0]
        );
        $this->assertSame(
            'order by "name" desc, "surname"',
            $this->q('[order]')->order('surname', false)->order('name', true)->render()[0]
        );
        // table name|alias included
        $this->assertSame(
            'order by "users"."name"',
            $this->q('[order]')->order('users.name')->render()[0]
        );
        // strange field names
        $this->assertSame(
            'order by "my name" desc',
            $this->q('[order]')->order('"my name" desc')->render()[0]
        );
        $this->assertSame(
            'order by "жук"',
            $this->q('[order]')->order('жук asc')->render()[0]
        );
        $this->assertSame(
            'order by "this is 💩"',
            $this->q('[order]')->order('this is 💩')->render()[0]
        );
        $this->assertSame(
            'order by "this is жук" desc',
            $this->q('[order]')->order('this is жук desc')->render()[0]
        );
        $this->assertSame(
            'order by * desc',
            $this->q('[order]')->order(['* desc'])->render()[0]
        );
        $this->assertSame(
            'order by "{}" desc',
            $this->q('[order]')->order(['{} desc'])->render()[0]
        );
        $this->assertSame(
            'order by "* desc"',
            $this->q('[order]')->order(new Expression('"* desc"'))->render()[0]
        );
        $this->assertSame(
            'order by "* desc"',
            $this->q('[order]')->order($this->q()->escape('* desc'))->render()[0]
        );
        $this->assertSame(
            'order by "* desc {}"',
            $this->q('[order]')->order($this->q()->escape('* desc {}'))->render()[0]
        );
        // custom sort order
        $this->assertSame(
            'order by "name" desc nulls last',
            $this->q('[order]')->order('name', 'desc nulls last')->render()[0]
        );
        $this->assertSame(
            'order by "name" nulls last',
            $this->q('[order]')->order('name', 'nulls last')->render()[0]
        );
    }

    /**
     * If first argument is array, second argument must not be used.
     *
     * @covers ::order
     */
    public function testOrderException1(): void
    {
        $this->expectException(Exception::class);
        $this->q('[order]')->order(['name', 'surname'], 'desc');
    }

    /**
     * Test Group.
     *
     * @covers ::_render_group
     * @covers ::group
     */
    public function testGroup(): void
    {
        $this->assertSame(
            'group by "gender"',
            $this->q('[group]')->group('gender')->render()[0]
        );
        $this->assertSame(
            'group by "gender", "age"',
            $this->q('[group]')->group('gender')->group('age')->render()[0]
        );
        // table name|alias included
        $this->assertSame(
            'group by "users"."gender"',
            $this->q('[group]')->group('users.gender')->render()[0]
        );
        // strange field names
        $this->assertSame(
            'group by "my name"',
            $this->q('[group]')->group('"my name"')->render()[0]
        );
        $this->assertSame(
            'group by "жук"',
            $this->q('[group]')->group('жук')->render()[0]
        );
        $this->assertSame(
            'group by "this is 💩"',
            $this->q('[group]')->group('this is 💩')->render()[0]
        );
        $this->assertSame(
            'group by "this is жук"',
            $this->q('[group]')->group('this is жук')->render()[0]
        );
        $this->assertSame(
            'group by date_format(dat, "%Y")',
            $this->q('[group]')->group(new Expression('date_format(dat, "%Y")'))->render()[0]
        );
        $this->assertSame(
            'group by date_format(dat, "%Y")',
            $this->q('[group]')->group('date_format(dat, "%Y")')->render()[0]
        );
    }

    /**
     * Test groupConcat.
     */
    public function testGroupConcatException(): void
    {
        // doesn't support groupConcat by default
        $this->expectException(Exception::class);
        $this->q()->groupConcat('foo');
    }

    /**
     * Test groupConcat.
     *
     * @covers ::groupConcat
     */
    public function testGroupConcat(): void
    {
        $q = new Mysql\Query();
        $this->assertSame('group_concat(`foo` separator \'-\')', $q->groupConcat('foo', '-')->render()[0]);

        $q = new Oracle\Query();
        $this->assertSame('listagg("foo", :a) within group (order by "foo")', $q->groupConcat('foo', '-')->render()[0]);

        $q = new Postgresql\Query();
        $this->assertSame('string_agg("foo", :a)', $q->groupConcat('foo', '-')->render()[0]);

        $q = new Sqlite\Query();
        $this->assertSame('group_concat("foo", :a)', $q->groupConcat('foo', '-')->render()[0]);
    }

    /**
     * Test expr().
     *
     * @covers ::expr
     */
    public function testExpr(): void
    {
        $this->assertSame(Expression::class, get_class($this->q()->expr('foo')));

        $q = new Mysql\Query();
        $this->assertSame(\Atk4\Data\Persistence\Sql\Mysql\Expression::class, get_class($q->expr('foo')));
    }

    /**
     * Test Join.
     *
     * @covers ::_render_join
     * @covers ::join
     */
    public function testJoin(): void
    {
        $this->assertSame(
            'left join "address" on "address"."id" = "address_id"',
            $this->q('[join]')->join('address')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."id" = "address_id"',
            $this->q('[join]')->join('address a')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."id" = "user"."address_id"',
            $this->q('[join]')->table('user')->join('address a')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."id" = "user"."my_address_id"',
            $this->q('[join]')->table('user')->join('address a', 'my_address_id')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."id" = "u"."address_id"',
            $this->q('[join]')->table('user', 'u')->join('address a')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."user_id" = "u"."id"',
            $this->q('[join]')->table('user', 'u')->join('address.user_id a')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."user_id" = "u"."id" '
            . 'left join "bank" "b" on "b"."id" = "u"."bank_id"',
            $this->q('[join]')->table('user', 'u')
                ->join('address.user_id', null, null, 'a')->join('bank', null, null, 'b')
                ->render()[0]
        );
        $this->assertSame(
            'left join "address" on "address"."user_id" = "u"."id" '
            . 'left join "bank" on "bank"."id" = "u"."bank_id"',
            $this->q('[join]')->table('user', 'u')
                ->join('address.user_id')->join('bank')->render()[0]
        );
        $this->assertSame(
            'left join "address" "a" on "a"."user_id" = "u"."id" '
            . 'left join "bank" "b" on "b"."id" = "u"."bank_id" '
            . 'left join "bank_details" on "bank_details"."id" = "bank"."details_id"',
            $this->q('[join]')->table('user', 'u')
                ->join('address.user_id', null, null, 'a')->join('bank', null, null, 'b')
                ->join('bank_details', 'bank.details_id')->render()[0]
        );

        $this->assertSame(
            'left join "address" "a" on a.name like u.pattern',
            $this->q('[join]')->table('user', 'u')
                ->join('address a', new Expression('a.name like u.pattern'))->render()[0]
        );
    }

    /**
     * Combined execution of where() clauses.
     *
     * @covers ::_render_where
     * @covers ::mode
     * @covers ::where
     */
    public function testCombinedWhere(): void
    {
        $this->assertSame(
            'select "name" from "employee" where "a" = :a',
            $this->q()
                ->field('name')->table('employee')->where('a', 1)
                ->render()[0]
        );

        $this->assertSame(
            'select "name" from "employee" where "employee"."a" = :a',
            $this->q()
                ->field('name')->table('employee')->where('employee.a', 1)
                ->render()[0]
        );

        /*
        $this->assertSame(
            'select "name" from "db"."employee" where "db"."employee"."a" = :a',
            $this->q()
                ->field('name')->table('db.employee')->where('db.employee.a',1)
                ->render()[0]
        );
         */

        $this->assertSame(
            'delete from "employee" where "employee"."a" = :a',
            $this->q()
                ->mode('delete')
                ->field('name')->table('employee')->where('employee.a', 1)
                ->render()[0]
        );

        $user_ids = $this->q()->table('expired_users')->field('user_id');

        $this->assertSame(
            'update "user" set "active"=:a  where "id" in (select "user_id" from "expired_users")',
            $this->q()
                ->table('user')
                ->where('id', 'in', $user_ids)
                ->set('active', 0)
                ->mode('update')
                ->render()[0]
        );
    }

    /**
     * Test OrWhere and AndWhere without where condition. Should ignore them.
     *
     * @covers ::_render_andwhere
     * @covers ::_render_orwhere
     * @covers ::_render_where
     * @covers ::andExpr
     * @covers ::orExpr
     * @covers ::where
     */
    public function testEmptyOrAndWhere(): void
    {
        $this->assertSame(
            '',
            $this->q()->orExpr()->render()[0]
        );

        $this->assertSame(
            '',
            $this->q()->andExpr()->render()[0]
        );
    }

    /**
     * Test insert, update and delete templates.
     *
     * @covers ::_render_set
     * @covers ::_render_set_fields
     * @covers ::_render_set_values
     * @covers ::mode
     * @covers ::set
     * @covers ::where
     */
    public function testInsertDeleteUpdate(): void
    {
        // delete template
        $this->assertSame(
            'delete from "employee" where "name" = :a',
            $this->q()
                ->field('name')->table('employee')->where('name', 1)
                ->mode('delete')
                ->render()[0]
        );

        // update template
        $this->assertSame(
            'update "employee" set "name"=:a',
            $this->q()
                ->field('name')->table('employee')->set('name', 1)
                ->mode('update')
                ->render()[0]
        );

        $this->assertSame(
            'update "employee" set "name"="name"+1',
            $this->q()
                ->field('name')->table('employee')->set('name', new Expression('"name"+1'))
                ->mode('update')
                ->render()[0]
        );

        // insert template
        $this->assertSame(
            'insert into "employee" ("name") values (:a)',
            $this->q()
                ->field('name')->table('employee')->set('name', 1)
                ->mode('insert')
                ->render()[0]
        );

        // set multiple fields
        $this->assertSame(
            'insert into "employee" ("time", "name") values (now(), :a)',
            $this->q()
                ->field('time')->field('name')->table('employee')
                ->set('time', new Expression('now()'))
                ->set('name', 'unknown')
                ->mode('insert')
                ->render()[0]
        );

        // set as array
        $this->assertSame(
            'insert into "employee" ("time", "name") values (now(), :a)',
            $this->q()
                ->field('time')->field('name')->table('employee')
                ->setMulti(['time' => new Expression('now()'), 'name' => 'unknown'])
                ->mode('insert')
                ->render()[0]
        );
    }

    /**
     * set() should return $this Query for chaining.
     *
     * @covers ::set
     */
    public function testSetReturnValue(): void
    {
        $q = $this->q();
        $this->assertSame($q, $q->set('id', 1));
    }

    /**
     * Value of type array is not supported by SQL.
     *
     * @covers ::set
     */
    public function testSetException1(): void
    {
        $this->expectException(Exception::class);
        $this->q()->set('name', []);
    }

    /**
     * Field name can be expression.
     *
     * @covers ::set
     *
     * @doesNotPerformAssertions
     */
    public function testSetException2(): void
    {
        $this->q()->set(new Expression('foo'), 1);
    }

    /**
     * Test nested OR and AND expressions.
     *
     * @covers ::_render_andwhere
     * @covers ::_render_orwhere
     * @covers ::andExpr
     * @covers ::orExpr
     * @covers ::where
     */
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
        $this->assertSame(
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
                        ->where('true')
                        ->where('false')
                )
        );
        $this->assertSame(
            'select "name" from "employee" where ("a" = :a or "b" = :b or ((true) and (false)))',
            $q->render()[0]
        );
    }

    public function testNestedOrAndHaving(): void
    {
        $q = $this->q();
        $q->table('employee')->field(new Expression('sum([])', ['amount']), 'salary')->group('type');
        $q->having(
            $q
                ->orExpr()
                ->having('a', 1)
                ->having('b', 1)
        );
        $this->assertSame(
            'select sum(:a) "salary" from "employee" group by "type" having ("a" = :b or "b" = :c)',
            $q->render()[0]
        );
    }

    public function testNestedOrAndHavingWithWhereException(): void
    {
        $q = $this->q();
        $q->table('employee')->field(new Expression('sum([])', ['amount']), 'salary')->group('type');
        $q->having(
            $q
                ->orExpr()
                ->where('a', 1)
                ->having('b', 1) // mixing triggers Exception on render
        );

        $this->expectException(Exception::class);
        $q->render();
    }

    /**
     * Test reset().
     *
     * @covers \Atk4\Data\Persistence\Sql\Expression::reset
     */
    public function testReset(): void
    {
        // reset everything
        $q = $this->q()->table('user')->where('name', 'John');
        $q->reset();
        $this->assertSame('select *', $q->render()[0]);

        // reset particular tag
        $q = $this->q()
            ->table('user')
            ->where('name', 'John')
            ->reset('where')
            ->where('surname', 'Doe');
        $this->assertSame('select * from "user" where "surname" = :a', $q->render()[0]);
    }

    /**
     * Test [option].
     *
     * @covers ::_render_option
     * @covers ::option
     */
    public function testOption(): void
    {
        // single option
        $this->assertSame(
            'select calc_found_rows * from "test"',
            $this->q()->table('test')->option('calc_found_rows')->render()[0]
        );
        // multiple options
        $this->assertSame(
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

        $this->assertSame(
            'select calc_found_rows "name" from "test"',
            $q->mode('select')->render()[0]
        );
        $this->assertSame(
            'insert ignore into "test" ("name") values (:a)',
            $q->mode('insert')->render()[0]
        );
        $this->assertSame(
            'update "test" set "name"=:a',
            $q->mode('update')->render()[0]
        );
    }

    /**
     * Test caseExpr (normal).
     *
     * @covers ::_render_case
     * @covers ::caseElse
     * @covers ::caseExpr
     * @covers ::caseWhen
     */
    public function testCaseExprNormal(): void
    {
        // Test normal form
        $s = $this->q()->caseExpr()
            ->caseWhen(['status', 'New'], 't2.expose_new')
            ->caseWhen(['status', 'like', '%Used%'], 't2.expose_used')
            ->caseElse(null)
            ->render()[0];
        $this->assertSame('case when "status" = :a then :b when "status" like :c then :d else :e end', $s);

        // with subqueries
        $age = new Expression('year(now()) - year(birth_date)');
        $q = $this->q()->table('user')->field($age, 'calc_age');

        $s = $this->q()->caseExpr()
            ->caseWhen(['age', '>', $q], 'Older')
            ->caseElse('Younger')
            ->render()[0];
        $this->assertSame('case when "age" > (select year(now()) - year(birth_date) "calc_age" from "user") then :a else :b end', $s);
    }

    /**
     * Test caseExpr (short form).
     *
     * @covers ::_render_case
     * @covers ::caseElse
     * @covers ::caseExpr
     * @covers ::caseWhen
     */
    public function testCaseExprShortForm(): void
    {
        $s = $this->q()->caseExpr('status')
            ->caseWhen('New', 't2.expose_new')
            ->caseWhen('Used', 't2.expose_used')
            ->caseElse(null)
            ->render()[0];
        $this->assertSame('case "status" when :a then :b when :c then :d else :e end', $s);

        // with subqueries
        $age = new Expression('year(now()) - year(birth_date)');
        $q = $this->q()->table('user')->field($age, 'calc_age');

        $s = $this->q()->caseExpr($q)
            ->caseWhen(100, 'Very old')
            ->caseElse('Younger')
            ->render()[0];
        $this->assertSame('case (select year(now()) - year(birth_date) "calc_age" from "user") when :a then :b else :c end', $s);
    }

    /**
     * Incorrect use of "when" method parameters.
     *
     * @doesNotPerformAssertions
     */
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

    /**
     * Tests exprNow() method.
     *
     * @covers ::exprNow
     */
    public function testExprNow(): void
    {
        $this->assertSame(
            'update "employee" set "hired"=current_timestamp()',
            $this->q()
                ->field('hired')->table('employee')->set('hired', $this->q()->exprNow())
                ->mode('update')
                ->render()[0]
        );

        $this->assertSame(
            'update "employee" set "hired"=current_timestamp(:a)',
            $this->q()
                ->field('hired')->table('employee')->set('hired', $this->q()->exprNow(2))
                ->mode('update')
                ->render()[0]
        );
    }

    /**
     * Test table name with dots in it - Select.
     */
    public function testTableNameDot1(): void
    {
        // render table
        $this->assertSame(
            '"foo"."bar"',
            $this->callProtected($this->q()->table('foo.bar'), '_render_table')
        );

        $this->assertSame(
            '"foo"."bar" "a"',
            $this->callProtected($this->q()->table('foo.bar', 'a'), '_render_table')
        );

        // where clause
        $this->assertSame(
            'select "name" from "db1"."employee" where "a" = :a',
            $this->q()
                ->field('name')->table('db1.employee')->where('a', 1)
                ->render()[0]
        );

        $this->assertSame(
            'select "name" from "db1"."employee" where "db1"."employee"."a" = :a',
            $this->q()
                ->field('name')->table('db1.employee')->where('db1.employee.a', 1)
                ->render()[0]
        );
    }

    /**
     * Test WITH.
     */
    public function testWith(): void
    {
        $q1 = $this->q()->table('salaries')->field('salary');

        $q2 = $this->q()
            ->with($q1, 'q1')
            ->table('q1');
        $this->assertSame('with "q1" as (select "salary" from "salaries")' . "\n"
            . 'select * from "q1"', $q2->render()[0]);

        $q2 = $this->q()
            ->with($q1, 'q1', null, true)
            ->table('q1');
        $this->assertSame('with recursive "q1" as (select "salary" from "salaries")' . "\n"
            . 'select * from "q1"', $q2->render()[0]);

        $q2 = $this->q()
            ->with($q1, 'q11', ['foo', 'qwe"ry'])
            ->with($q1, 'q12', ['bar', 'baz'], true) // this one is recursive
            ->table('q11')
            ->table('q12');
        $this->assertSame('with recursive "q11" ("foo", "qwe""ry") as (select "salary" from "salaries"),' . "\n"
            . '"q12" ("bar", "baz") as (select "salary" from "salaries")' . "\n" . 'select * from "q11", "q12"', $q2->render()[0]);

        // now test some more useful reql life query
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
        $this->assertSame(
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

    public function testExists(): void
    {
        $this->assertSame(
            'select exists (select * from "contacts" where "first_name" = :a)',
            $this->q()->table('contacts')->where('first_name', 'John')->exists()->render()[0]
        );

        $q = new Oracle\Query();
        $this->assertSame(
            'select case when exists(select * from "contacts" where "first_name" = :xxaaaa) then 1 else 0 end from "DUAL"',
            $q->table('contacts')->where('first_name', 'John')->exists()->render()[0]
        );

        $q = new Mssql\Query();
        $this->assertSame(
            'select case when exists(select * from [contacts] where [first_name] = :a) then 1 else 0 end',
            $q->table('contacts')->where('first_name', 'John')->exists()->render()[0]
        );
    }
}
