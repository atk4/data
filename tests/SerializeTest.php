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

        $this->assertEquals(['data' => 'a:1:{s:3:"foo";s:3:"bar";}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));

        $f->serialize = 'json';
        $this->assertEquals(['data' => '{"foo":"bar"}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));
    }
}
