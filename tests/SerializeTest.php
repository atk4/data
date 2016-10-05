<?php

namespace atk4\data\tests;

use atk4\data\Model;

class SerializeTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasicSerialize()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['encode' => 'serialize']);

        $this->assertEquals('N', $db->typecastSaveField($f, ['foo' => 'bar']));
    }
}
