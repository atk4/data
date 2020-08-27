<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Various tests to make sure transactions work OK.
 */
class TransactionTest extends \atk4\schema\PhpunitTestCase
{
    public function testAtomicOperations()
    {
        $db = new Persistence\Sql($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ];
        $this->setDb($a);

        $m = new Model($db, 'item');
        $m->addField('name');
        $m->load(2);

        $m->onHook(Model::HOOK_AFTER_SAVE, function ($m) {
            throw new \Exception('Awful thing happened');
        });
        $m->set('name', 'XXX');

        try {
            $m->save();
        } catch (\Exception $e) {
        }

        $this->assertSame('Sue', $this->getDb()['item'][2]['name']);

        $m->onHook(Model::HOOK_AFTER_DELETE, function ($m) {
            throw new \Exception('Awful thing happened');
        });

        try {
            $m->delete();
        } catch (\Exception $e) {
        }

        $this->assertSame('Sue', $this->getDb()['item'][2]['name']);
    }

    public function testBeforeSaveHook()
    {
        $self = $this;
        $db = new Persistence\Sql($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
            ],
        ];
        $this->setDb($a);

        // test insert
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->onHook(Model::HOOK_BEFORE_SAVE, function ($model, $is_update) use ($self) {
            $self->assertFalse($is_update);
        });
        $m->save(['name' => 'Foo']);

        // test update
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->onHook(Model::HOOK_AFTER_SAVE, function ($model, $is_update) use ($self) {
            $self->assertTrue($is_update);
        });
        $m->loadBy('name', 'John')->save(['name' => 'Foo']);
    }

    public function testAfterSaveHook()
    {
        $self = $this;
        $db = new Persistence\Sql($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
            ],
        ];
        $this->setDb($a);

        // test insert
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->onHook(Model::HOOK_AFTER_SAVE, function ($model, $is_update) use ($self) {
            $self->assertFalse($is_update);
        });
        $m->save(['name' => 'Foo']);

        // test update
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->onHook(Model::HOOK_AFTER_SAVE, function ($model, $is_update) use ($self) {
            $self->assertTrue($is_update);
        });
        $m->loadBy('name', 'John')->save(['name' => 'Foo']);
    }

    public function testOnRollbackHook()
    {
        $self = $this;
        $db = new Persistence\Sql($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
            ],
        ];
        $this->setDb($a);

        // test insert
        $m = new Model($db, 'item');
        $m->addField('name');
        $m->addField('foo');

        $hook_called = false;
        $values = [];
        $m->onHook(Model::HOOK_ROLLBACK, function ($mm, $e) use (&$hook_called, &$values) {
            $hook_called = true;
            $values = $mm->get(); // model field values are still the same no matter we rolled back
            $mm->breakHook(false); // if we break hook and return false then exception is not thrown, but rollback still happens
        });

        // this will fail because field foo is not in DB and call onRollback hook
        $m->setMulti(['name' => 'Jane', 'foo' => 'bar']);
        $m->save();

        $this->assertTrue($hook_called);
        $this->assertSame($m->get(), $values);
    }
}
