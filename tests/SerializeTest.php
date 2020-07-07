<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence;

class SerializeTest extends \atk4\schema\PhpunitTestCase
{
    public function testBasicSerialize()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['serialize' => 'serialize']);

        $this->assertSame(
            ['data' => 'a:1:{s:3:"foo";s:3:"bar";}'],
            $db->typecastSaveRow(
                $m,
                ['data' => ['foo' => 'bar']]
            )
        );
        $this->assertSame(
            ['data' => ['foo' => 'bar']],
            $db->typecastLoadRow(
                $m,
                ['data' => 'a:1:{s:3:"foo";s:3:"bar";}']
            )
        );

        $f->serialize = 'json';
        $f->type = 'array';
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

    public function testSerializeErrorJson()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['type' => 'array', 'serialize' => 'json']);

        $this->expectException(Exception::class);
        $db->typecastLoadRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    public function testSerializeErrorJson2()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['type' => 'array', 'serialize' => 'json']);

        // recursive array - json can't encode that
        $a = [];
        $a[] = &$a;

        $this->expectException(Exception::class);
        $db->typecastSaveRow($m, ['data' => ['foo' => 'bar', 'recursive' => $a]]);
    }

    /*
     * THIS IS NOT POSSIBLE BECAUSE unserialize() produces error
     * and not exception
     */

    /*
    public function testSerializeErrorSerialize()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['serialize' => 'serialize']);
        $this->expectException(Exception::class);
        $this->assertEquals(
            ['data' => ['foo' => 'bar']]
            , $db->typecastLoadRow($m,
            ['data' => 'a:1:{s:3:"foo";s:3:"bar"; OPS']
        ));
    }
     */
}
