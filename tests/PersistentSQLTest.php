<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class PersistentSQLTest extends \atk4\schema\PhpunitTestCase
{
    /**
     * Test constructor.
     */
    public function testLoadArray()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $this->assertSame('John', $m->get('name'));

        $m->load(2);
        $this->assertSame('Jones', $m->get('surname'));
        $m->set('surname', 'Smith');
        $m->save();

        $m->load(1);
        $this->assertSame('John', $m->get('name'));

        $m->load(2);
        $this->assertSame('Smith', $m->get('surname'));
    }

    public function testPersistenceInsert()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $ids = [];
        foreach ($a->get('user') as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }

        $m->load($ids->get(0));
        $this->assertSame('John', $m->get('name'));

        $m->load($ids->get(1));
        $this->assertSame('Jones', $m->get('surname'));
        $m->set('surname', 'Smith');
        $m->save();

        $m->load($ids->get(0));
        $this->assertSame('John', $m->get('name'));

        $m->load($ids->get(1));
        $this->assertSame('Smith', $m->get('surname'));
    }

    public function testModelInsert()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $ms = [];
        foreach ($a->get('user') as $id => $row) {
            $ms[] = $m->insert($row);
        }

        $this->assertSame('John', $m->load($ms->get(0))['name']);

        $this->assertSame('Jones', $m->load($ms->get(1))['surname']);
    }

    public function testModelSaveNoReload()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        // insert new record, model id field
        $m->reload_after_save = false;
        $m->save(['name' => 'Jane', 'surname' => 'Doe']);
        $this->assertSame('Jane', $m->get('name'));
        $this->assertSame('Doe', $m->get('surname'));
        $this->assertEquals(3, $m->id);
        // id field value is set with new id value even if reload_after_save = false
        $this->assertEquals(3, $m->get($m->id_field));
    }

    public function testModelInsertRows()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDB($a, false); // create empty table

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->import($a->get('user')); // import data

        $this->assertEquals(2, $m->action('count')->getOne());
    }

    public function testPersistenceDelete()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $ids = [];
        foreach ($a->get('user') as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }
        $this->assertFalse($m->loaded());

        $m->delete($ids->get(0));
        $this->assertFalse($m->loaded());

        $m->load($ids->get(1));
        $this->assertSame('Jones', $m->get('surname'));
        $m->set('surname', 'Smith');
        $m->save();

        $m->tryLoad($ids->get(0));
        $this->assertFalse($m->loaded());

        $m->load($ids->get(1));
        $this->assertSame('Smith', $m->get('surname'));
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('surname');

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ['id' => 2, 'name' => 'Sarah', 'surname' => 'Jones'],
        ], $m->export());

        $this->assertSame([
            ['surname' => 'Smith'],
            ['surname' => 'Jones'],
        ], $m->export(['surname']));
    }
}
