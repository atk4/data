<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

class FieldTest extends SQLTestCase
{
    public function testDirty1()
    {
        $m = new Model();
        $m->addField('foo', ['default' => 'abc']);

        $this->assertEquals(false, $m->isDirty('foo'));

        $m['foo'] = 'abc';
        $this->assertEquals(false, $m->isDirty('foo'));

        $m['foo'] = 'bca';
        $this->assertEquals(true, $m->isDirty('foo'));

        $m['foo'] = 'abc';
        $this->assertEquals(false, $m->isDirty('foo'));

        $m->data['foo'] = 'xx';

        $m['foo'] = 'abc';
        $this->assertEquals(true, $m->isDirty('foo'));

        $m['foo'] = 'bca';
        $this->assertEquals(true, $m->isDirty('foo'));

        $m['foo'] = 'xx';
        $this->assertEquals(false, $m->isDirty('foo'));
    }

    /**
     * @expectedException Exception
     */
    public function testReadOnly1()
    {
        $m = new Model();
        $m->addField('foo', ['read_only' => true]);
        $m['foo'] = 'bar';
    }

    public function testReadOnly2()
    {
        $this->markTestSkipped('TODO: readonly setting same value should be OK');
        $m = new Model();
        $m->addField('foo', ['read_only' => true, 'default' => 'abc']);
        $m['foo'] = 'abc';
    }

    public function testReadOnly3()
    {
        $this->markTestSkipped('TODO: readonly setting same value should be OK');
        $m = new Model();
        $m->addField('foo', ['read_only' => true, 'default' => 'abc']);
        $m->data['foo'] = 'xx';
        $m['foo'] = 'xx';
    }

    public function testPersist()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'item');
        $m->addField('name', ['never_persist' => true]);
        $m->addField('surname', ['never_save' => true]);
        $m->load(1);

        $this->assertNull($m['name']);
        $this->assertEquals('Smith', $m['surname']);

        $m['name'] = 'Bill';
        $m['surname'] = 'Stalker';
        $m->save();
        $this->assertEquals($a, $this->getDB());

        $m->reload();
        $this->assertEquals('Smith', $m['surname']);
        $m->getElement('surname')->never_save = false;
        $m['surname'] = 'Stalker';
        $m->save();
        $a['item'][1]['surname'] = 'Stalker';
        $this->assertEquals($a, $this->getDB());

        $m->addHook('beforeSave', function ($m) {
            if ($m->isDirty('name')) {
                $m['surname'] = $m['name'];
                unset($m['name']);
            } elseif ($m->isDirty('surname')) {
                $m['name'] = $m['surname'];
                unset($m['surname']);
            }
        });

        $m['name'] = 'X';
        $m->save();


        $a['item'][1]['surname'] = 'X';

        $this->assertEquals($a, $this->getDB());
        $this->assertNull($m['name']);
        $this->assertEquals('X', $m['surname']);

        $m['surname'] = 'Y';
        $m->save();

        $this->assertEquals($a, $this->getDB());
        $this->assertEquals('Y', $m['name']);
        $this->assertEquals('X', $m['surname']);
    }

    public function testTitle()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ];
        $this->setDB($a);

        $c = new Model($db, 'category');
        $c->addField('name');

        $m = new Model($db, 'user');
        $m->addField('name');
        $m->hasOne('category_id', $c)
            ->addTitle();

        $m->load(1);

        $this->assertEquals('John', $m['name']);
        $this->assertEquals('Programmer', $m['category']);

        $m->insert(['Peter', 'category' => 'Sales']);

        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'category_id' => 2],
                2 => ['id' => 2, 'name' => 'Peter', 'surname' => null, 'category_id' => 3],
            ],
            'category' => [
                1 => ['id' => 1, 'name' => 'General'],
                2 => ['id' => 2, 'name' => 'Programmer'],
                3 => ['id' => 3, 'name' => 'Sales'],
            ],
        ];
        $this->assertEquals($a, $this->getDB());
    }

    /**
     * @expectedException Exception
     */
    public function testStrict1()
    {
        $m = new Model();
        $m->addField('foo');
        $m['baz'] = 'bar';
    }

    public function testStrict2()
    {
        $m = new Model(['strict_field_check' => false]);
        $m->addField('foo');
        $m['baz'] = 'bar';
    }
}
