<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Persistence;

/**
 * Various tests to make sure transactions work OK.
 */
class TransactionTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testAtomicOperations(): void
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($db, ['table' => 'item']);
        $m->addField('name');
        $m = $m->load(2);

        $m->onHook(Model::HOOK_AFTER_SAVE, static function ($m) {
            throw new \Exception('Awful thing happened');
        });
        $m->set('name', 'XXX');

        try {
            $m->save();
        } catch (\Exception $e) {
        }

        $this->assertSame('Sue', $this->getDb()['item'][2]['name']);

        $m->onHook(Model::HOOK_AFTER_DELETE, static function ($m) {
            throw new \Exception('Awful thing happened');
        });

        try {
            $m->delete();
        } catch (\Exception $e) {
        }

        $this->assertSame('Sue', $this->getDb()['item'][2]['name']);
    }

    public function testBeforeSaveHook(): void
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                ['name' => 'John'],
            ],
        ]);

        // test insert
        $m = new Model($db, ['table' => 'item']);
        $m->addField('name');
        $testCase = $this;
        $m->onHookShort(Model::HOOK_BEFORE_SAVE, static function (bool $isUpdate) use ($testCase) {
            $testCase->assertFalse($isUpdate);
        });
        $m->createEntity()->save(['name' => 'Foo']);

        // test update
        $m = new Model($db, ['table' => 'item']);
        $m->addField('name');
        $m->onHookShort(Model::HOOK_AFTER_SAVE, static function (bool $isUpdate) use ($testCase) {
            $testCase->assertTrue($isUpdate);
        });
        $m = $m->loadBy('name', 'John')->save(['name' => 'Foo']);
    }

    public function testAfterSaveHook(): void
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                ['name' => 'John'],
            ],
        ]);

        // test insert
        $m = new Model($db, ['table' => 'item']);
        $m->addField('name');
        $testCase = $this;
        $m->onHookShort(Model::HOOK_AFTER_SAVE, static function (bool $isUpdate) use ($testCase) {
            $testCase->assertFalse($isUpdate);
        });
        $m->createEntity()->save(['name' => 'Foo']);

        // test update
        $m = new Model($db, ['table' => 'item']);
        $m->addField('name');
        $m->onHookShort(Model::HOOK_AFTER_SAVE, static function (bool $isUpdate) use ($testCase) {
            $testCase->assertTrue($isUpdate);
        });
        $m = $m->loadBy('name', 'John')->save(['name' => 'Foo']);
    }

    public function testOnRollbackHook(): void
    {
        $db = new Persistence\Sql($this->db->connection);
        $this->setDb([
            'item' => [
                ['name' => 'John'],
            ],
        ]);

        // test insert
        $m = new Model($db, ['table' => 'item']);
        $m->addField('name');
        $m->addField('foo');

        $hook_called = false;
        $values = [];
        $m->onHook(Model::HOOK_ROLLBACK, static function (Model $model, \Exception $e) use (&$hook_called, &$values) {
            $hook_called = true;
            $values = $model->get(); // model field values are still the same no matter we rolled back
            $model->breakHook(false); // if we break hook and return false then exception is not thrown, but rollback still happens
        });

        // this will fail because field foo is not in DB and call onRollback hook
        $m = $m->createEntity();
        $m->setMulti(['name' => 'Jane', 'foo' => 'bar']);
        $m->save();

        $this->assertTrue($hook_called);
        $this->assertSame($m->get(), $values);
    }
}
