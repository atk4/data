<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql\WithDb;

use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\ExecuteException;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception\InvalidFieldNameException;

class TransactionTest extends TestCase
{
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

    protected function setupEmployeeTable(): void
    {
        $model = new Model($this->db, ['table' => 'employee']);
        $model->addField('name');
        $this->createMigrator($model)->create();
    }

    protected function executeOnePassingInsert(): void
    {
        $rowsBefore = $this->q('employee')->getRows();
        try {
            $this->q('employee')
                ->setMulti(['name' => 'John'])
                ->mode('insert')->executeStatement();
        } finally {
            $rowsAfter = $this->q('employee')->getRows();
            self::assertSame($rowsBefore, array_slice($rowsAfter, 0, -1));
            self::assertSame('John', end($rowsAfter)['name']);
        }
    }

    protected function executeOneFailingInsert(): void
    {
        $rowsBefore = $this->q('employee')->getRows();
        try {
            $this->q('employee')
                ->setMulti(['name' => 'John', 'non_existent' => 'bar'])
                ->mode('insert')->executeStatement();
        } catch (ExecuteException $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());

            throw $e;
        } finally {
            self::assertSame($rowsBefore, $this->q('employee')->getRows());
        }
    }

    public function testBeginAndCommitTransaction(): void
    {
        $this->setupEmployeeTable();

        $this->executeOnePassingInsert();

        $this->getConnection()->beginTransaction();
        $this->executeOnePassingInsert();
        $this->getConnection()->commit();

        self::assertSame([
            ['id' => '1', 'name' => 'John'],
            ['id' => '2', 'name' => 'John'],
        ], $this->q('employee')->getRows());
    }

    public function testBeginAndRollbackTransaction(): void
    {
        $this->setupEmployeeTable();

        $this->executeOnePassingInsert();

        $this->getConnection()->beginTransaction();
        $this->executeOnePassingInsert();
        $this->getConnection()->rollBack();

        self::assertSame([
            ['id' => '1', 'name' => 'John'],
        ], $this->q('employee')->getRows());

        $this->getConnection()->beginTransaction();
        $this->executeOnePassingInsert();
        $this->executeOnePassingInsert();
        $this->getConnection()->rollBack();

        self::assertSame([
            ['id' => '1', 'name' => 'John'],
        ], $this->q('employee')->getRows());
    }

    public function testAtomicSimple(): void
    {
        $this->setupEmployeeTable();

        $this->getConnection()->atomic(function () {
            $this->executeOnePassingInsert();
            $this->executeOnePassingInsert();
        });

        try {
            $this->getConnection()->atomic(function () {
                $this->executeOnePassingInsert();
                $this->executeOneFailingInsert();
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'John'],
            ['id' => '2', 'name' => 'John'],
        ], $this->q('employee')->getRows());
    }

    public function testAtomicNested(): void
    {
        $this->setupEmployeeTable();

        $this->getConnection()->atomic(function () {
            $this->getConnection()->atomic(function () {
                $this->executeOnePassingInsert();
            });
        });

        try {
            $this->getConnection()->atomic(function () {
                $this->executeOnePassingInsert();
                $this->getConnection()->atomic(function () {
                    $this->executeOnePassingInsert();
                    $this->executeOneFailingInsert();
                });
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'John'],
        ], $this->q('employee')->getRows());

        try {
            $this->getConnection()->atomic(function () {
                $this->executeOnePassingInsert();
                $this->getConnection()->atomic(function () {
                    $this->executeOnePassingInsert();
                });
                $this->executeOneFailingInsert();
            });
        } catch (Exception $e) {
            self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
        }

        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'John'],
        ], $this->q('employee')->getRows());
    }

    public function testAtomicSavepointAfterAtomicFailure(): void
    {
        $this->setupEmployeeTable();

        $this->getConnection()->atomic(function () {
            $this->executeOnePassingInsert();
            try {
                $this->getConnection()->atomic(function () {
                    $this->executeOneFailingInsert();
                });
            } catch (Exception $e) {
                self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
            }
            $this->executeOnePassingInsert();
        });

        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'John'],
            ['id' => '2', 'name' => 'John'],
        ], $this->q('employee')->getRows());
    }

    public function testAtomicSavepointAfterQueryFailure(): void
    {
        $this->setupEmployeeTable();

        $this->getConnection()->atomic(function () {
            $this->executeOnePassingInsert();
            try {
                $this->executeOneFailingInsert();
            } catch (Exception $e) {
                self::assertInstanceOf(InvalidFieldNameException::class, $e->getPrevious());
            }
            $this->executeOnePassingInsert();
        });

        self::assertSameExportUnordered([
            ['id' => '1', 'name' => 'John'],
            ['id' => '2', 'name' => 'John'],
        ], $this->q('employee')->getRows());
    }
}
