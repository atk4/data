<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Array_\Db;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Persistence\Array_\Db\Row;
use Atk4\Data\Persistence\Array_\Db\Table;

class RowTest extends TestCase
{
    public function testUpdateValuesUnknownColumnException(): void
    {
        $table = new Table('t');
        $table->addColumn('foo');

        $row = $table->addRow(Row::class, []);
        $row->updateValues(['foo' => 1]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column name does not exist');
        $row->updateValues(['bar' => 1]);
    }
}
