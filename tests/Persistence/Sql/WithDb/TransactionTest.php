<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception\InvalidFieldNameException;

class TransactionTest extends TestCase
{
    protected function setupTables(): void
    {
        $model = new Model($this->db, ['table' => 'employee']);
        $model->addField('name');

        $this->createMigrator($model)->create();
    }

    /**
     * @param string|Expression                 $table
     * @param ($table is null ? never : string) $alias
     */
    protected function q($table = null, string $alias = null): Query
    {
        $q = $this->getConnection()->dsql();
        if ($table !== null) {
            $q->table($table, $alias);
        }

        return $q;
    }

    public function testCommitUnopenedTransactionException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Commit failed');
        $this->getConnection()->commit();
    }

    public function testCommitUnopenedTransactionAfterTransactionException(): void
    {
        $this->getConnection()->beginTransaction();
        $this->getConnection()->commit();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Commit failed');
        $this->getConnection()->commit();
    }

    public function testRollbackUnopenedTransactionException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rollback failed');
        $this->getConnection()->rollBack();
    }

    public function testRollbackUnopenedTransactionAfterTransactionException(): void
    {
        $this->getConnection()->beginTransaction();
        $this->getConnection()->rollBack();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Rollback failed');
        $this->getConnection()->rollBack();
    }

    /**
     * Tests simple and nested transactions.
     * TODO split this test.
     */
    public function testTransactions(): void
    {
        $this->setupTables();

        self::assertSame(
            '0',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // without transaction, ignoring exceptions
        try {
            $this->q('employee')
                ->setMulti(['id' => 1, 'name' => 'John'])
                ->mode('insert')->executeStatement();
            $this->q('employee')
                ->setMulti(['id' => 2, 'name' => 'John', 'non_existent' => 'bar'])
                ->mode('insert')->executeStatement();
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSame(
            '1',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // 1-level transaction: begin, insert, 2, rollback, 1
        $this->getConnection()->beginTransaction();
        $this->q('employee')
            ->setMulti(['id' => 3, 'name' => 'John'])
            ->mode('insert')->executeStatement();
        self::assertSame(
            '2',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        $this->getConnection()->rollBack();
        self::assertSame(
            '1',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // atomic method, rolls back everything inside atomic() callback in case of exception
        try {
            $this->getConnection()->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John'])
                    ->mode('insert')->executeStatement();
                $this->q('employee')
                    ->setMulti(['id' => 4, 'name' => 'John', 'non_existent' => 'bar'])
                    ->mode('insert')->executeStatement();
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSame(
            '1',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->getConnection()->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John'])
                    ->mode('insert')->executeStatement();

                // success, in, fail, out, fail
                $this->getConnection()->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John'])
                        ->mode('insert')->executeStatement();
                    $this->q('employee')
                        ->setMulti(['id' => 5, 'name' => 'John', 'non_existent' => 'bar'])
                        ->mode('insert')->executeStatement();
                });
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSame(
            '1',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->getConnection()->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John'])
                    ->mode('insert')->executeStatement();

                // success, in, success, out, fail
                $this->getConnection()->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John'])
                        ->mode('insert')->executeStatement();
                });

                $this->q('employee')
                    ->setMulti(['id' => 5, 'name' => 'John', 'non_existent' => 'bar'])
                    ->mode('insert')->executeStatement();
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSame(
            '1',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // atomic method, nested atomic transaction, rolls back everything
        try {
            $this->getConnection()->atomic(function () {
                $this->q('employee')
                    ->setMulti(['id' => 3, 'name' => 'John'])
                    ->mode('insert')->executeStatement();

                // success, in, fail, out, catch exception
                $this->getConnection()->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John', 'non_existent' => 'bar'])
                        ->mode('insert')->executeStatement();
                });
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSame(
            '1',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // atomic method, success - commit
        $this->getConnection()->atomic(function () {
            $this->q('employee')
                ->setMulti(['id' => 3, 'name' => 'John'])
                ->mode('insert')->executeStatement();
        });

        self::assertSame(
            '2',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );
    }

    public function testInTransaction(): void
    {
        self::assertFalse($this->getConnection()->inTransaction());

        $this->getConnection()->beginTransaction();
        self::assertTrue($this->getConnection()->inTransaction());

        $this->getConnection()->rollBack();
        self::assertFalse($this->getConnection()->inTransaction());

        $this->getConnection()->beginTransaction();
        $this->getConnection()->commit();
        self::assertFalse($this->getConnection()->inTransaction());
    }
}
