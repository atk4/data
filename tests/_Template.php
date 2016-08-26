<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

class RandomSQLTests extends SQLTestCase
{
    public function sampleTest()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'item');
        $m->addField('name');
        $m->load(2);

        $this->assertNotNull($m->id);

        $this->assertEquals($a, $this->getDB());
    }

}
