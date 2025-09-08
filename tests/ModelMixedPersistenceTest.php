<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;

class ModelMixedPersistenceTest extends TestCase
{
    public function testArrayTable(): void
    {
        $dbArray = new Persistence\Array_([
            'user' => [
                10 => ['id' => 10, 'name' => 'John'],
                20 => ['id' => 20, 'name' => 'Peter'],
            ],
        ]);

        $mArray = new Model($dbArray, ['table' => 'user']);
        $mArray->addField('name');

        $m = new Model($this->db, ['table' => $mArray]);
        $m->addField('name');

        $this->assertSameSql(
            'select `id`, `name` from (select :a `id`, :b `name` union all select :c, :d) `_tm`',
            $m->action('select')->render()[0]
        );

        self::assertSameExportUnordered([
            ['id' => 10, 'name' => 'John'],
            ['id' => 20, 'name' => 'Peter'],
        ], $m->export());
    }
}
