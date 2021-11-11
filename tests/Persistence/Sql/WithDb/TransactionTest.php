<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\Migration;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;

class TransactionTest extends TestCase
{
    /** @var Connection */
    protected $c;

    private function dropDbIfExists(): void
    {
        (new Migration($this->c))->table('employee')->dropIfExists();
    }

    protected function setUp(): void
    {
        $this->c = Connection::connect($_ENV['DB_DSN'], $_ENV['DB_USER'], $_ENV['DB_PASSWORD']);

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

        parent::tearDown();
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

    public function testCommitException1(): void
    {
        // try to commit when not in transaction
        $this->expectException(Exception::class);
        $this->c->commit();
    }

    public function testCommitException2(): void
    {
        // try to commit when not in transaction anymore
        $this->c->beginTransaction();
        $this->c->commit();
        $this->expectException(Exception::class);
        $this->c->commit();
    }

    public function testRollbackException1(): void
    {
        // try to rollback when not in transaction
        $this->expectException(Exception::class);
        $this->c->rollBack();
    }

    public function testRollbackException2(): void
    {
        // try to rollback when not in transaction anymore
        $this->c->beginTransaction();
        $this->c->rollBack();
        $this->expectException(Exception::class);
        $this->c->rollBack();
    }

    /**
     * Tests simple and nested transactions.
     */
    public function testTransactions(): void
    {
        // truncate table, prepare
        $this->q('employee')->truncate();
        $this->assertSame(
            '0',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // without transaction, ignoring exceptions
        try {
            $this->q('employee')
                ->setMulti(['id' => 1, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                ->insert();
            $this->q('employee')
                ->setMulti(['id' => 2, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
                ->insert();
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // 1-level transaction: begin, insert, 2, rollback, 1
        $this->c->beginTransaction();
        $this->q('employee')
            ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
            ->insert();
        $this->assertSame(
            '2',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        $this->c->rollBack();
        $this->assertSame(
            '1',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // atomic method, rolls back everything inside atomic() callback in case of exception
        try {
            $this->c->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->insert();
                $this->q('employee')
                    ->setMulti(['id' => 4, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
                    ->insert();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->c->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->insert();

                // success, in, fail, out, fail
                $this->c->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                        ->insert();
                    $this->q('employee')
                        ->setMulti(['id' => 5, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
                        ->insert();
                });

                $this->q('employee')
                    ->setMulti(['id' => 6, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
                    ->insert();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->c->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->insert();

                // success, in, success, out, fail
                $this->c->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                        ->insert();
                });

                $this->q('employee')
                    ->setMulti(['id' => 5, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
                    ->insert();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->c->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->insert();

                // success, in, fail, out, catch exception
                $this->c->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'FOO' => 'bar', 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                        ->insert();
                });

                $this->q('employee')
                    ->setMulti(['id' => 5, 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
                    ->insert();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '1',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );

        // atomic method, success - commit
        try {
            $this->c->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->insert();
            });
        } catch (\Exception $e) {
            // ignore
        }

        $this->assertSame(
            '2',
            $this->q('employee')->field(new Expression('count(*)'))->getOne()
        );
    }

    /**
     * Tests inTransaction().
     */
    public function testInTransaction(): void
    {
        // inTransaction tests
        $this->assertFalse(
            $this->c->inTransaction()
        );

        $this->c->beginTransaction();
        $this->assertTrue(
            $this->c->inTransaction()
        );

        $this->c->rollBack();
        $this->assertFalse(
            $this->c->inTransaction()
        );

        $this->c->beginTransaction();
        $this->c->commit();
        $this->assertFalse(
            $this->c->inTransaction()
        );
    }
}
