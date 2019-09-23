<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Various tests to make sure transactions work OK.
 */
class TransactionTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testAtomicOperations()
    {
        $db = new Persistence\SQL($this->db->connection);
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

        $m->addHook('afterSave', function ($m) {
            throw new \Exception('Awful thing happened');
        });
        $m['name'] = 'XXX';

        try {
            $m->save();
        } catch (\Exception $e) {
        }

        $this->assertEquals('Sue', $this->getDB()['item'][2]['name']);

        $m->addHook('afterDelete', function ($m) {
            throw new \Exception('Awful thing happened');
        });

        try {
            $m->delete();
        } catch (\Exception $e) {
        }

        $this->assertEquals('Sue', $this->getDB()['item'][2]['name']);
    }

    public function testBeforeSaveHook()
    {
        $self = $this;
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
            ], ];
        $this->setDB($a);

        // test insert
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->addHook('beforeSave', function ($model, $is_update) use ($self) {
            $self->assertFalse($is_update);
        });
        $m->save(['name'=>'Foo']);

        // test update
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->addHook('afterSave', function ($model, $is_update) use ($self) {
            $self->assertTrue($is_update);
        });
        $m->loadBy('name', 'John')->save(['name'=>'Foo']);
    }

    public function testAfterSaveHook()
    {
        $self = $this;
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
            ], ];
        $this->setDB($a);

        // test insert
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->addHook('afterSave', function ($model, $is_update) use ($self) {
            $self->assertFalse($is_update);
        });
        $m->save(['name'=>'Foo']);

        // test update
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->addHook('afterSave', function ($model, $is_update) use ($self) {
            $self->assertTrue($is_update);
        });
        $m->loadBy('name', 'John')->save(['name'=>'Foo']);
    }

    public function testOnRollbackHook()
    {
        $self = $this;
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
            ], ];
        $this->setDB($a);

        // test insert
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->addField('foo');

        $hook_called = false;
        $values = [];
        $m->addHook('onRollback', function ($mm, $e) use (&$hook_called, &$values) {
            $hook_called = true;
            $values = $mm->get(); // model field values are still the same no matter we rolled back
            $mm->breakHook(false); // if we break hook and return false then exception is not thrown, but rollback still happens
        });

        // this will fail because field foo is not in DB and call onRollback hook
        $m->set(['name'=>'Jane', 'foo'=>'bar']);
        $m->save();

        $this->assertTrue($hook_called);
        $this->assertEquals($m->get(), $values);
    }
}
