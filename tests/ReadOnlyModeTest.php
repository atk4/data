<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * Tests cases when model have to work with data that does not have ID field
 */
class ReadOnlyModeTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public $m;

    public function setUp()
    {
        parent::setUp();
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $this->m = new Model($db, ['user', 'read_only'=>true]);

        $this->m->addFields(['name', 'gender']);
    }

    /**
     * Basic operation should work just fine on model without ID.
     */
    public function testBasic()
    {
        $this->m->tryLoadAny();
        $this->assertEquals('John', $this->m['name']);

        $this->m->setOrder('name desc');
        $this->m->tryLoadAny();
        $this->assertEquals('Sue', $this->m['name']);

        $n = [];
        foreach ($this->m as $row) {
            $n[] = $row['name'];
        }
        $this->assertEquals(['Sue', 'John'], $n);
    }

    /**
     * Read only model can be loaded just fine.
     */
    public function testLoad()
    {
        $this->m->load(1);
    }

    /**
     * Model cannot be saved.
     *
     * @expectedException Exception
     */
    public function testLoadSave()
    {
        $this->m->load(1);
        $this->m['name'] = 'X';
        $this->m->save();
    }

    /**
     * Insert should fail too.
     *
     * @expectedException Exception
     */
    public function testInsert()
    {
        $this->m->insert(['name'=>'Joe']);
    }

    /**
     * Different attempt that should also fail.
     *
     * @expectedException Exception
     */
    public function testSave1()
    {
        $this->m->tryLoadAny();
        $this->m->saveAndUnload();
    }

    /**
     * Conditions should work fine.
     */
    public function testLoadBy()
    {
        $this->m->loadBy('name', 'Sue');
        $this->assertEquals('Sue', $this->m['name']);
    }

    public function testLoadCondition()
    {
        $this->m->addCondition('name', 'Sue');
        $this->m->loadAny();
        $this->assertEquals('Sue', $this->m['name']);
    }

    /**
     * @expectedException Exception
     */
    public function testFailDelete1()
    {
        $this->m->delete(1);
    }
}
