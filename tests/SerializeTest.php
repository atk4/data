<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

class SerializeTest extends \atk4\schema\PhpunitTestCase
{
    public function testBasicSerialize()
    {
        $db = new Persistence\SQL($this->db->connection);
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

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testSerializeErrorJSON()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['type' => 'array', 'serialize' => 'json']);

        $db->typecastLoadRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testSerializeErrorJSON2()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['type' => 'array', 'serialize' => 'json']);

        // recursive array - json can't encode that
        $a = [];
        $a[] = &$a;

        $db->typecastSaveRow($m, ['data' => ['foo' => 'bar', 'recursive' => $a]]);
    }

    /*
     * @expectedException Exception
     *
     * THIS IS NOT POSSIBLE BECAUSE unserialize() produces error
     * and not exception
     */

    /*
    public function testSerializeErrorSerialize()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['serialize' => 'serialize']);
        $this->assertEquals(
            ['data' => ['foo' => 'bar']]
            , $db->typecastLoadRow($m,
            ['data' => 'a:1:{s:3:"foo";s:3:"bar"; OPS']
        ));
    }
     */
}
