<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;

class SelectTest extends AtkPhpunit\TestCase
{
    /** @var Connection */
    protected $c;

    private function dropDbIfExists(): void
    {
        if ($this->c->getDatabasePlatform() instanceof OraclePlatform) {
            $this->c->connection()->executeQuery('begin
                execute immediate \'drop table "employee"\';
            exception
                when others then
                    if sqlcode != -942 then
                        raise;
                    end if;
            end;');
        } else {
            $this->c->connection()->executeQuery('DROP TABLE IF EXISTS employee');
        }
    }

    protected function setUp(): void
    {
        $this->c = Connection::connect($GLOBALS['DB_DSN'], $GLOBALS['DB_USER'], $GLOBALS['DB_PASSWD']);

        $this->dropDbIfExists();

        $strType = $this->c->getDatabasePlatform() instanceof OraclePlatform ? 'varchar2' : 'varchar';
        $boolType = ['mssql' => 'bit', 'oracle' => 'number(1)'][$this->c->getDatabasePlatform()->getName()] ?? 'bool';
        $fixIdentifiersFunc = function ($sql) {
            return preg_replace_callback('~(?:\'(?:\'\'|\\\\\'|[^\'])*\')?+\K"([^\'"()\[\]{}]*?)"~s', function ($matches) {
                if ($this->c->getDatabasePlatform() instanceof MySQLPlatform) {
                    return '`' . $matches[1] . '`';
                } elseif ($this->c->getDatabasePlatform() instanceof SQLServer2012Platform) {
                    return '[' . $matches[1] . ']';
                }

                return '"' . $matches[1] . '"';
            }, $sql);
        };
        $this->c->connection()->executeQuery($fixIdentifiersFunc('CREATE TABLE "employee" ("id" int not null, "name" ' . $strType . '(100), "surname" ' . $strType . '(100), "retired" ' . $boolType . ', ' . ($this->c->getDatabasePlatform() instanceof OraclePlatform ? 'CONSTRAINT "employee_pk" ' : '') . 'PRIMARY KEY ("id"))'));
        foreach ([
            ['id' => 1, 'name' => 'Oliver', 'surname' => 'Smith', 'retired' => false],
            ['id' => 2, 'name' => 'Jack', 'surname' => 'Williams', 'retired' => true],
            ['id' => 3, 'name' => 'Harry', 'surname' => 'Taylor', 'retired' => true],
            ['id' => 4, 'name' => 'Charlie', 'surname' => 'Lee', 'retired' => false],
        ] as $row) {
            $this->c->connection()->executeQuery($fixIdentifiersFunc('INSERT INTO "employee" (' . implode(', ', array_map(function ($v) {
                return '"' . $v . '"';
            }, array_keys($row))) . ') VALUES(' . implode(', ', array_map(function ($v) {
                if (is_bool($v)) {
                    if ($this->c->getDatabasePlatform() instanceof PostgreSQL94Platform) {
                        return $v ? 'true' : 'false';
                    }

                    return $v ? 1 : 0;
                } elseif (is_int($v)) {
                    return $v;
                }

                return '\'' . $v . '\'';
            }, $row)) . ')'));
        }
    }

    protected function tearDown(): void
    {
        $this->dropDbIfExists();

        $this->c = null; // @phpstan-ignore-line
    }

    /**
     * @param mixed  $table
     * @param string $alias
     */
    private function q($table = null, string $alias = null): Query
    {
        $q = $this->c->dsql();

        // add table to query if specified
        if ($table !== null) {
            $q->table($table, $alias);
        }

        return $q;
    }

    /**
     * @param string|array $template
     * @param array        $args
     */
    private function e($template = [], array $args = null): Expression
    {
        return $this->c->expr($template, $args);
    }

    public function testBasicQueries(): void
    {
        $this->assertSame(4, count($this->q('employee')->getRows()));

        $this->assertSame(
            ['name' => 'Oliver', 'surname' => 'Smith'],
            $this->q('employee')->field('name,surname')->getRow()
        );

        $this->assertSame(
            ['surname' => 'Williams'],
            $this->q('employee')->field('surname')->where('retired', true)->getRow()
        );

        $this->assertSame(
            '4',
            $this->q()->field(new Expression('2+2'))->getOne()
        );

        $this->assertSame(
            '4',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        $names = [];
        foreach ($this->q('employee')->where('retired', false)->getIterator() as $row) {
            $names[] = $row['name'];
        }

        $this->assertSame(
            ['Oliver', 'Charlie'],
            $names
        );

        $this->assertSame(
            [['now' => '4']],
            $this->q()->field(new Expression('2+2'), 'now')->getRows()
        );

        /*
         * PostgreSQL needs to have values cast, to make the query work.
         * But CAST(.. AS int) does not work in mysql. So we use two different tests..
         * (CAST(.. AS int) will work on mariaDB, whereas mysql needs it to be CAST(.. AS signed))
         */
        if ($this->c->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->assertSame(
                [['now' => '6']],
                $this->q()->field(new Expression('CAST([] AS int)+CAST([] AS int)', [3, 3]), 'now')->getRows()
            );
        } else {
            $this->assertSame(
                [['now' => '6']],
                $this->q()->field(new Expression('[]+[]', [3, 3]), 'now')->getRows()
            );
        }

        $this->assertSame(
            '5',
            $this->q()->field(new Expression('COALESCE([], \'5\')', [null]), 'null_test')->getOne()
        );
    }

    public function testExpression(): void
    {
        /*
         * PostgreSQL, at least versions before 10, needs to have the string cast to the
         * correct datatype.
         * But using CAST(.. AS CHAR) will return one single character on postgresql, but the
         * entire string on mysql.
         */
        if ($this->c->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->c->getDatabasePlatform() instanceof SQLServer2012Platform) {
            $this->assertSame(
                'foo',
                $this->e('select CAST([] AS VARCHAR)', ['foo'])->getOne()
            );
        } elseif ($this->c->getDatabasePlatform() instanceof OraclePlatform) {
            $this->assertSame(
                'foo',
                $this->e('select CAST([] AS VARCHAR2(100)) FROM DUAL', ['foo'])->getOne()
            );
        } else {
            $this->assertSame(
                'foo',
                $this->e('select CAST([] AS CHAR)', ['foo'])->getOne()
            );
        }
    }

    public function testOtherQueries(): void
    {
        // truncate table
        $this->q('employee')->truncate();
        $this->assertSame(
            '0',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // insert
        $this->q('employee')
            ->set(['id' => 1, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
            ->insert();
        $this->q('employee')
            ->set(['id' => 2, 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
            ->insert();
        $this->assertSame(
            [['id' => '1', 'name' => 'John'], ['id' => '2', 'name' => 'Jane']],
            $this->q('employee')->field('id,name')->order('id')->getRows()
        );

        // update
        $this->q('employee')
            ->where('name', 'John')
            ->set('name', 'Johnny')
            ->update();
        $this->assertSame(
            [['id' => '1', 'name' => 'Johnny'], ['id' => '2', 'name' => 'Jane']],
            $this->q('employee')->field('id,name')->order('id')->getRows()
        );

        // replace
        if ($this->c->getDatabasePlatform() instanceof PostgreSQL94Platform || $this->c->getDatabasePlatform() instanceof SQLServer2012Platform || $this->c->getDatabasePlatform() instanceof OraclePlatform) {
            $this->q('employee')
                ->set(['name' => 'Peter', 'surname' => 'Doe', 'retired' => true])
                ->where('id', 1)
                ->update();
        } else {
            $this->q('employee')
                ->set(['id' => 1, 'name' => 'Peter', 'surname' => 'Doe', 'retired' => true])
                ->replace();
        }

        // In SQLite replace is just like insert, it just checks if there is
        // duplicate key and if it is it deletes the row, and inserts the new
        // one, otherwise it just inserts.
        // So order of records after REPLACE in SQLite will be [Jane, Peter]
        // not [Peter, Jane] as in MySQL, which in theory does the same thing,
        // but returns [Peter, Jane] - in original order.
        // That's why we add usort here.
        $data = $this->q('employee')->field('id,name')->getRows();
        usort($data, function ($a, $b) {
            return $a['id'] - $b['id'];
        });
        $this->assertSame(
            [['id' => '1', 'name' => 'Peter'], ['id' => '2', 'name' => 'Jane']],
            $data
        );

        // delete
        $this->q('employee')
            ->where('retired', true)
            ->delete();
        $this->assertSame(
            [['id' => '2', 'name' => 'Jane']],
            $this->q('employee')->field('id,name')->getRows()
        );
    }

    public function testEmptyGetOne(): void
    {
        // truncate table
        $this->q('employee')->truncate();
        $this->expectException(Exception::class);
        $this->q('employee')->field('name')->getOne();
    }

    public function testWhereExpression(): void
    {
        $this->assertSame(
            [['id' => '2', 'name' => 'Jack', 'surname' => 'Williams', 'retired' => '1']],
            $this->q('employee')->where('retired', true)->where($this->q()->expr('{}=[] or {}=[]', ['surname', 'Williams', 'surname', 'Smith']))->getRows()
        );
    }

    public function testExecuteException(): void
    {
        $this->expectException(\Atk4\Data\Persistence\Sql\ExecuteException::class);

        try {
            $this->q('non_existing_table')->field('non_existing_field')->getOne();
        } catch (\Atk4\Data\Persistence\Sql\ExecuteException $e) {
            // test error code
            $unknownFieldErrorCode = [
                'sqlite' => 1,        // SQLSTATE[HY000]: General error: 1 no such table: non_existing_table
                'mysql' => 1146,      // SQLSTATE[42S02]: Base table or view not found: 1146 Table 'non_existing_table' doesn't exist
                'postgresql' => 7,    // SQLSTATE[42P01]: Undefined table: 7 ERROR: relation "non_existing_table" does not exist
                'mssql' => 208,       // SQLSTATE[42S02]: Invalid object name 'non_existing_table'
                'oracle' => 942,      // SQLSTATE[HY000]: ORA-00942: table or view does not exist
            ][$this->c->getDatabasePlatform()->getName()];
            $this->assertSame($unknownFieldErrorCode, $e->getCode());

            // test debug query
            $expectedQuery = [
                'mysql' => 'select `non_existing_field` from `non_existing_table`',
                'mssql' => 'select [non_existing_field] from [non_existing_table]',
            ][$this->c->getDatabasePlatform()->getName()] ?? 'select "non_existing_field" from "non_existing_table"';
            $this->assertSame(preg_replace('~\s+~', '', $expectedQuery), preg_replace('~\s+~', '', $e->getDebugQuery()));

            throw $e;
        }
    }
}
