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

        // truncate table, prepare
        $this->q('employee')->mode('truncate')->executeStatement();
        self::assertSame(
            '0',
            $this->q('employee')->field($this->getConnection()->expr('count(*)'))->getOne()
        );

        // without transaction, ignoring exceptions
        try {
            $this->q('employee')
                ->setMulti(['id' => 1, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                ->mode('insert')->executeStatement();
            $this->q('employee')
                ->setMulti(['id' => 2, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
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
            ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
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
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->mode('insert')->executeStatement();
                $this->q('employee')
                    ->setMulti(['id' => 4, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
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
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->mode('insert')->executeStatement();

                // success, in, fail, out, fail
                $this->getConnection()->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                        ->mode('insert')->executeStatement();
                    $this->q('employee')
                        ->setMulti(['id' => 5, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
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
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->mode('insert')->executeStatement();

                // success, in, success, out, fail
                $this->getConnection()->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                        ->mode('insert')->executeStatement();
                });

                $this->q('employee')
                    ->setMulti(['id' => 5, 'FOO' => 'bar', 'name' => 'Jane', 'surname' => 'Doe', 'retired' => false])
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
                    ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
                    ->mode('insert')->executeStatement();

                // success, in, fail, out, catch exception
                $this->getConnection()->atomic(function () {
                    $this->q('employee')
                        ->setMulti(['id' => 4, 'FOO' => 'bar', 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
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
                ->setMulti(['id' => 3, 'name' => 'John', 'surname' => 'Doe', 'retired' => true])
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
