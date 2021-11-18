<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence\SqlPersistence;
use Atk4\Data\Schema\TestCase;

class SerializeTest extends TestCase
{
    public function testBasicSerialize(): void
    {
        $db = new SqlPersistence($this->db->connection);
        $m = new Model($db, ['table' => 'job']);

        $f = $m->addField('data', ['type' => 'object']);

        $this->assertSame(
            ['data' => 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}'],
            $db->typecastSaveRow(
                $m,
                ['data' => (object) ['foo' => 'bar']]
            )
        );
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $db->typecastLoadRow(
                $m,
                ['data' => 'a:1:{s:3:"foo";s:3:"bar";}']
            )
        );

        $f->type = 'json';
        $this->assertSame(
            ['data' => '{"foo":"bar"}'],
            $db->typecastSaveRow(
                $m,
                ['data' => ['foo' => 'bar']]
            )
        );
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $db->typecastLoadRow(
                $m,
                ['data' => '{"foo":"bar"}']
            )
        );
    }

    public function testSerializeErrorJson(): void
    {
        $db = new SqlPersistence($this->db->connection);
        $m = new Model($db, ['table' => 'job']);

        $f = $m->addField('data', ['type' => 'json']);

        $this->expectException(Exception::class);
        $db->typecastLoadRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    public function testSerializeErrorJson2(): void
    {
        $db = new SqlPersistence($this->db->connection);
        $m = new Model($db, ['table' => 'job']);

        $f = $m->addField('data', ['type' => 'json']);

        // recursive array - json can't encode that
        $dbData = [];
        $dbData[] = &$dbData;

        $this->expectException(Exception::class);
        $db->typecastSaveRow($m, ['data' => ['foo' => 'bar', 'recursive' => $dbData]]);
    }

    public function testSerializeErrorSerialize(): void
    {
        $db = new SqlPersistence($this->db->connection);
        $m = new Model($db, ['table' => 'job']);

        $this->expectException(Exception::class);
        $f = $m->addField('data', ['type' => 'object']);
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $db->typecastLoadRow($m, ['data' => 'a:1:{s:3:"foo";s:3:"bar"; OPS'])
        );
    }
}
