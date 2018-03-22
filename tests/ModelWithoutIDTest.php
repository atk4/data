<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * Tests cases when model have to work with data that does not have ID field
 */
class ModelWithoutIDTest extends \atk4\schema\PHPUnit_SchemaTestCase
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
        $this->m = new Model($db, ['user', 'id_field'=>false]);

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
            $this->assertNull($row->id);
        }
        $this->assertEquals(['Sue', 'John'], $n);
    }

    /**
     * @expectedException Exception
     */
    public function testFail1()
    {
        $this->m->load(1);
    }

    /**
     * Inserting into model without ID should be OK.
     */
    public function testInsert()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $this->m->insert(['name'=>'Joe']);
        $this->assertEquals(3, $this->m->action('count')->getOne());
    }

    /**
     * Since no ID is set, a new record will be created if saving is attempted.
     */
    public function testSave1()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $this->m->tryLoadAny();
        $this->m->saveAndUnload();

        $this->assertEquals(3, $this->m->action('count')->getOne());
    }

    /**
     * Calling save will always create new record.
     */
    public function testSave2()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $this->m->tryLoadAny();
        $this->m->save();

        $this->assertEquals(3, $this->m->action('count')->getOne());
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
        $this->m->delete(4);
    }

    /**
     * Additional checks are done if ID is manually set.
     *
     * @expectedException Exception
     */
    public function testFailDelete2()
    {
        $this->m->id = 4;
        $this->m->delete();
    }

    /**
     * Additional checks are done if ID is manually set.
     *
     * @expectedException Exception
     */
    public function testFailUpdate()
    {
        $this->m->id = 1;
        $this->m['name'] = 'foo';
        $this->m->saveAndUnload();
    }
}
