<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * Tests cases when model have to work with data that does not have ID field
 */
class ReadOnlyModeTest extends \atk4\schema\PhpunitTestCase
{
    public $m;

    protected function setUp(): void
    {
        parent::setUp();
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDb($a);

        $db = new Persistence\Sql($this->db->connection);
        $this->m = new Model($db, ['user', 'read_only' => true]);

        $this->m->addFields(['name', 'gender']);
    }

    /**
     * Basic operation should work just fine on model without ID.
     */
    public function testBasic()
    {
        $this->m->tryLoadAny();
        $this->assertSame('John', $this->m->get('name'));

        $this->m->setOrder('name', 'desc');
        $this->m->tryLoadAny();
        $this->assertSame('Sue', $this->m->get('name'));

        $this->assertEquals([1 => 'John', 2 => 'Sue'], $this->m->getTitles());
    }

    /**
     * Read only model can be loaded just fine.
     */
    public function testLoad()
    {
        $this->m->load(1);
        $this->assertTrue($this->m->loaded());
    }

    /**
     * Model cannot be saved.
     */
    public function testLoadSave()
    {
        $this->m->load(1);
        $this->m->set('name', 'X');
        $this->expectException(Exception::class);
        $this->m->save();
    }

    /**
     * Insert should fail too.
     */
    public function testInsert()
    {
        $this->expectException(Exception::class);
        $this->m->insert(['name' => 'Joe']);
    }

    /**
     * Different attempt that should also fail.
     */
    public function testSave1()
    {
        $this->m->tryLoadAny();
        $this->expectException(Exception::class);
        $this->m->saveAndUnload();
    }

    /**
     * Conditions should work fine.
     */
    public function testLoadBy()
    {
        $this->m->loadBy('name', 'Sue');
        $this->assertSame('Sue', $this->m->get('name'));
    }

    public function testLoadCondition()
    {
        $this->m->addCondition('name', 'Sue');
        $this->m->loadAny();
        $this->assertSame('Sue', $this->m->get('name'));
    }

    public function testFailDelete1()
    {
        $this->expectException(Exception::class);
        $this->m->delete(1);
    }
}
