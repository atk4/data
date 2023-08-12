<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class SerializeTest extends TestCase
{
    public function testBasicSerialize(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'object']);

        self::assertSame(
            ['data' => 'O:8:"stdClass":1:{s:3:"foo";s:3:"bar";}'],
            $this->db->typecastSaveRow(
                $m,
                ['data' => (object) ['foo' => 'bar']]
            )
        );
        self::assertSame(
            ['data' => ['foo' => 'bar']],
            $this->db->typecastLoadRow(
                $m,
                ['data' => 'a:1:{s:3:"foo";s:3:"bar";}']
            )
        );

        $m->getField('data')->type = 'json';
        self::assertSame(
            ['data' => '{"foo":"bar"}'],
            $this->db->typecastSaveRow(
                $m,
                ['data' => ['foo' => 'bar']]
            )
        );
        self::assertSame(
            ['data' => ['foo' => 'bar']],
            $this->db->typecastLoadRow(
                $m,
                ['data' => '{"foo":"bar"}']
            )
        );
    }

    public function testSerializeErrorJson(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'json']);

        $this->expectException(Exception::class);
        $this->db->typecastLoadRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    public function testSerializeErrorJson2(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'json']);

        // recursive array - json can't encode that
        $dbData = [];
        $dbData[] = &$dbData;

        $this->expectException(Exception::class);
        $this->db->typecastSaveRow($m, ['data' => ['foo' => 'bar', 'recursive' => $dbData]]);
    }

    public function testSerializeErrorSerialize(): void
    {
        $m = new Model($this->db, ['table' => 'job']);
        $m->addField('data', ['type' => 'object']);

        $this->expectException(Exception::class);
        $this->db->typecastLoadRow($m, ['data' => 'a:1:{s:3:"foo";s:3:"bar"; OPS']);
    }
}
