<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;

class TransactionTest extends TestCase
{
    /** @var Connection */
    protected $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->c = $this->db->connection;

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
