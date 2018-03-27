<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class PersistentSQLTest extends \atk4\schema\PHPUnit_SchemaTestCase
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
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Smith', $m['surname']);
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
        foreach ($a['user'] as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }

        $m->load($ids[0]);
        $this->assertEquals('John', $m['name']);

        $m->load($ids[1]);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->load($ids[0]);
        $this->assertEquals('John', $m['name']);

        $m->load($ids[1]);
        $this->assertEquals('Smith', $m['surname']);
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
        foreach ($a['user'] as $id => $row) {
            $ms[] = $m->insert($row);
        }

        $this->assertEquals('John', $m->load($ms[0])['name']);

        $this->assertEquals('Jones', $m->load($ms[1])['surname']);
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

        $m->import($a['user']); // import data

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
        foreach ($a['user'] as $id => $row) {
            $ids[] = $this->db->insert($m, $row);
        }
        $this->assertEquals(false, $m->loaded());

        $m->delete($ids[0]);
        $this->assertEquals(false, $m->loaded());

        $m->load($ids[1]);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->tryLoad($ids[0]);
        $this->assertEquals(false, $m->loaded());

        $m->load($ids[1]);
        $this->assertEquals('Smith', $m['surname']);
    }
}
