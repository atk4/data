<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * THIS IS NOT A TEST. This file is a template which you can duplicate
 * to create a new TestCase.
 */
class _Template extends \atk4\schema\PHPUnit_SchemaTestCase
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
