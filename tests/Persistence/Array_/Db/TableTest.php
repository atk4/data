<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Array_\Db;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Persistence\Array_\Db\Row;
use Atk4\Data\Persistence\Array_\Db\Table;

class TableTest extends TestCase
{
    public function testTableNameNumericException(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Name must be a non-empty and non-numeric');
        new Table('10');
    }

    public function testAddColumnNameEmptyException(): void
    {
        $table = new Table('t');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Name must be a non-empty and non-numeric');
        $table->addColumn('');
    }

    public function testAddColumnNameNumericException(): void
    {
        $table = new Table('t');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Name must be a non-empty and non-numeric');
        $table->addColumn('10.0');
    }

    public function testAddColumnDuplicateException(): void
    {
        $table = new Table('t');

        $table->addColumn('foo');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column name already exists');
        $table->addColumn('foo');
    }

    public function testBeforeAfterUpdateRow(): void
    {
        $table = new class('t') extends Table {
            /** @var list<array{'before'|'after', Row, array<string, mixed>, 3?: array<string, mixed>}> */
            private array $log = [];

            #[\Override]
            protected function beforeUpdateRow(Row $row, $newData): void
            {
                parent::beforeUpdateRow($row, $newData);

                $this->log[] = ['before', $row, $newData];
            }

            #[\Override]
            protected function afterUpdateRow(Row $row, $oldData, $newData): void
            {
                parent::afterUpdateRow($row, $oldData, $newData);

                $this->log[] = ['after', $row, $oldData, $newData];
            }

            /**
             * @return list<array{'before'|'after', Row, array<string, mixed>, 3?: array<string, mixed>}>
             */
            public function getAndClearLog(): array
            {
                $res = $this->log;
                $this->log = [];

                return $res;
            }
        };

        $table->addColumn('foo');
        self::assertSame([], $table->getAndClearLog());

        $rowA = $table->addRow(Row::class, []);
        self::assertSame([
            ['before', $rowA, ['foo' => null]],
            ['after', $rowA, [], ['foo' => null]],
        ], $table->getAndClearLog());

        $rowB = $table->addRow(Row::class, ['foo' => 5]);
        self::assertSame([
            ['before', $rowB, ['foo' => 5]],
            ['after', $rowB, [], ['foo' => 5]],
        ], $table->getAndClearLog());

        $table->addColumn('bar');
        self::assertSame([
            ['before', $rowA, ['bar' => null]],
            ['after', $rowA, [], ['bar' => null]],
            ['before', $rowB, ['bar' => null]],
            ['after', $rowB, [], ['bar' => null]],
        ], $table->getAndClearLog());

        $rowA->updateValues(['foo' => 2]);
        self::assertSame([
            ['before', $rowA, ['foo' => 2]],
            ['after', $rowA, ['foo' => null], ['foo' => 2]],
        ], $table->getAndClearLog());

        $rowB->updateValues(['foo' => 6]);
        self::assertSame([
            ['before', $rowB, ['foo' => 6]],
            ['after', $rowB, ['foo' => 5], ['foo' => 6]],
        ], $table->getAndClearLog());

        $rowB->updateValues(['foo' => 7, 'bar' => 8]);
        self::assertSame([
            ['before', $rowB, ['foo' => 7, 'bar' => 8]],
            ['after', $rowB, ['foo' => 6, 'bar' => null], ['foo' => 7, 'bar' => 8]],
        ], $table->getAndClearLog());

        $rowB->updateValues([]);
        self::assertSame([], $table->getAndClearLog());

        $rowB->updateValues(['foo' => 7]);
        self::assertSame([], $table->getAndClearLog());

        self::assertSame([$rowA->getRowIndex() => $rowA, $rowB->getRowIndex() => $rowB], iterator_to_array($table->getRows()));

        $table->deleteRow($rowA);
        self::assertSame([
            ['before', $rowA, []],
            ['after', $rowA, ['foo' => 2, 'bar' => null], []],
        ], $table->getAndClearLog());
        self::assertSame([$rowB->getRowIndex() => $rowB], iterator_to_array($table->getRows()));
    }

    public function testGetRowsUsingIndex(): void
    {
        $table = new Table('t');
        $table->addColumn('foo');

        $rowA = $table->addRow(Row::class, ['foo' => 1]);
        $rowB = $table->addRow(Row::class, ['foo' => 1]);
        $rowC = $table->addRow(Row::class, ['foo' => '1']);

        self::assertSame([$rowA, $rowB], $table->getRowsUsingIndex('foo', 1));
        self::assertSame([$rowC], $table->getRowsUsingIndex('foo', '1'));

        $rowD = $table->addRow(Row::class, ['foo' => null]);
        $rowE = $table->addRow(Row::class, ['foo' => null]);
        $rowF = $table->addRow(Row::class, ['foo' => '']);

        self::assertSame([$rowD, $rowE], $table->getRowsUsingIndex('foo', null));
        self::assertSame([$rowF], $table->getRowsUsingIndex('foo', ''));
    }

    public function testGetRowUsingIndex(): void
    {
        $table = new Table('t');
        $table->addColumn('foo');

        $table->addRow(Row::class, ['foo' => 1]);
        $table->addRow(Row::class, ['foo' => 1]);
        $rowC = $table->addRow(Row::class, ['foo' => '1']);

        self::assertSame($rowC, $table->getRowUsingIndex('foo', '1'));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Index is not unique, more than one row was found');
        $table->getRowUsingIndex('foo', 1);
    }
}
