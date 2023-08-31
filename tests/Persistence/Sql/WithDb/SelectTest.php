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
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class SelectTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
     * @param string|Expression $table
     */
    protected function q($table = null, string $alias = null): Query
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
        // So order of records after REPLACE in SQLite will be [Jane, Peter]
        // not [Peter, Jane] as in MySQL, which in theory does the same thing,
        // but returns [Peter, Jane] - in original order.
        // That's why we add usort here.
        $data = $this->q('employee')->field('id')->field('name')->getRows();
        usort($data, static function ($a, $b) {
            return $a['id'] - $b['id']; // @phpstan-ignore-line
        });
        self::assertSame([
            ['id' => '1', 'name' => 'Peter'],
            ['id' => '2', 'name' => 'Jane'],
        ], $data);

        // delete
        $this->q('employee')
            ->where('retired', true)
            ->mode('delete')->executeStatement();
        self::assertSame([
            ['id' => '2', 'name' => 'Jane'],
        ], $this->q('employee')->field('id')->field('name')->getRows());
    }

    public function testEmptyGetOne(): void
    {
        $this->q('employee')->mode('truncate')->executeStatement();
        $q = $this->q('employee')->field('name');

        $this->expectException(Exception::class);
        $q->getOne();
    }

    public function testSelectUnexistingColumnException(): void
    {
        $q = $this->q('employee')->field('Sqlite must use backticks for identifier escape');

        $this->expectException(Exception::class);
        $q->executeStatement();
    }

    public function testWhereExpression(): void
    {
        self::assertSame([
            ['id' => '2', 'name' => 'Jack', 'surname' => 'Williams', 'retired' => '1'],
        ], $this->q('employee')->where('retired', true)->where($this->q()->expr('{}=[] or {}=[]', ['surname', 'Williams', 'surname', 'Smith']))->getRows());
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

        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSame([
                'select exists (select * from `contacts` where `first_name` = :a)',
                [':a' => 'John'],
            ], $q->render());
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSame([
                'select exists (select * from "contacts" where "first_name" = :a)',
                [':a' => 'John'],
            ], $q->render());
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
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
            self::assertSame([
                'select exists (select * from `contacts` where `first_name` = :a)',
                [':a' => 'John'],
            ], $q->render());
        }
    }

    public function testExecuteException(): void
    {
        $q = $this->q('non_existing_table')->field('non_existing_field');

        $this->expectException(ExecuteException::class);
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
            $maxIdExpr = $this->q()->table($table)->field($this->e('max({})', [$pk]));
            if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
                $query = $this->q()->table('INFORMATION_SCHEMA.TABLES')
                    ->field($this->e('greatest({} - 1, (' . $maxIdExpr->render()[0] . '))', ['AUTO_INCREMENT']))
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

        self::assertSame([
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
        ], $m->export());
    }
}
