<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Schema\TestCase;

class TestCaseTest extends TestCase
{
    public function testGetSetDb(): void
    {
        $this->assertSame([], $this->getDb([]));
        $this->assertSame([], $this->getDb());

        $dbData = [
            'user' => [
                ['name' => 'John', 'age' => '25'],
                ['name' => 'Steve', 'age' => '30'],
            ],
        ];
        $dbDataWithId = array_map(function ($rows) {
            $rowsWithId = [];
            $id = 1;
            foreach ($rows as $row) {
                $rowsWithId[$id] = array_merge(['id' => (string) $id], $row);
                ++$id;
            }

            return $rowsWithId;
        }, $dbData);

        $this->setDb($dbData);
        $dbDataGet1 = $this->getDb(['user']);
        $this->assertSameExportUnordered($dbDataWithId, $dbDataGet1);
        $this->assertSameExportUnordered($dbDataWithId, $this->getDb());
        $this->assertSameExportUnordered($dbData, $this->getDb(null, true));

        $this->setDb($dbData);
        $dbDataGet2 = $this->getDb(['user']);
        $this->assertSameExportUnordered($dbDataWithId, $dbDataGet2);
        $this->assertSameExportUnordered($dbDataWithId, $this->getDb());
        $this->assertSame($dbDataGet1, $dbDataGet2);

        $this->setDb($dbDataGet1);
        $dbDataGet3 = $this->getDb(['user']);
        $this->assertSameExportUnordered($dbDataWithId, $dbDataGet3);
        $this->assertSameExportUnordered($dbDataWithId, $this->getDb());
        $this->assertSame($dbDataGet1, $dbDataGet3);
    }
}
