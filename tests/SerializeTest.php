<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

class SerializeTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasicSerialize()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['serialize' => 'serialize']);

        $this->assertEquals(
            ['data' => 'a:1:{s:3:"foo";s:3:"bar";}'], $db->typecastSaveRow($m,
            ['data' => ['foo' => 'bar']]
        ));
        $this->assertEquals(
            ['data' => ['foo' => 'bar']], $db->typecastLoadRow($m,
            ['data' => 'a:1:{s:3:"foo";s:3:"bar";}']
        ));

        $f->serialize = 'json';
        $f->type = 'array';
        $this->assertEquals(
            ['data' => '{"foo":"bar"}'], $db->typecastSaveRow($m,
            ['data' => ['foo' => 'bar']]
        ));
        $this->assertEquals(
            ['data' => ['foo' => 'bar']], $db->typecastLoadRow($m,
            ['data' => '{"foo":"bar"}']
        ));
    }

    /**
     * @expectedException Exception
     */
    public function testSerializeErrorJSON()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['type' => 'array', 'serialize' => 'json']);

        $db->typecastLoadRow($m, ['data' => '{"foo":"bar" OPS']);
    }

    /**
     * @expectedException Exception
     */
    public function testSerializeErrorJSON2()
    {
        $db = new Persistence_SQL($this->db->connection);
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
        $db = new Persistence_SQL($this->db->connection);
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
