<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class TransactionTest extends TestCase
{
    public function testAtomicOperations(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $m = $m->load(2);

        $m->onHook(Model::HOOK_AFTER_SAVE, static function () {
            throw new \Exception('Awful thing happened');
        });
        $m->set('name', 'XXX');

        try {
            $m->save();
        } catch (\Exception $e) {
        }

        self::assertSame('Sue', $this->getDb()['item'][2]['name']);

        $m->onHook(Model::HOOK_AFTER_DELETE, static function (Model $model) {
            throw new \Exception('Awful thing happened');
        });

        try {
            $m->delete();
        } catch (\Exception $e) {
        }

        self::assertSame('Sue', $this->getDb()['item'][2]['name']);
    }

    public function testBeforeSaveHook(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
            ],
        ]);

        // test insert
        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $testCase = $this;
        $m->onHookShort(Model::HOOK_BEFORE_SAVE, static function (bool $isUpdate) {
            self::assertFalse($isUpdate);
        });
        $m->createEntity()->save(['name' => 'Foo']);

        // test update
        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $m->onHookShort(Model::HOOK_AFTER_SAVE, static function (bool $isUpdate) {
            self::assertTrue($isUpdate);
        });
        $m->loadBy('name', 'John')->save(['name' => 'Foo']);
    }

    public function testAfterSaveHook(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
            ],
        ]);

        // test insert
        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $testCase = $this;
        $m->onHookShort(Model::HOOK_AFTER_SAVE, static function (bool $isUpdate) {
            self::assertFalse($isUpdate);
        });
        $m->createEntity()->save(['name' => 'Foo']);

        // test update
        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $m->onHookShort(Model::HOOK_AFTER_SAVE, static function (bool $isUpdate) {
            self::assertTrue($isUpdate);
        });
        $m->loadBy('name', 'John')->save(['name' => 'Foo']);
    }

    public function testOnRollbackHook(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
            ],
        ]);

        // test insert
        $m = new Model($this->db, ['table' => 'item']);
        $m->addField('name');
        $m->addField('foo');

        $hookCalled = false;
        $values = [];
        $m->onHook(Model::HOOK_ROLLBACK, static function (Model $model, \Exception $e) use (&$hookCalled, &$values) {
            $hookCalled = true;
            $values = $model->get(); // model field values are still the same no matter we rolled back
            $model->breakHook(false); // if we break hook and return false then exception is not thrown, but rollback still happens
        });

        // this will fail because field foo is not in DB and call onRollback hook
        $m = $m->createEntity();
        $m->setMulti(['name' => 'Jane', 'foo' => 'bar']);
        $m->save();

        self::assertTrue($hookCalled);
        self::assertSame($m->get(), $values);
    }
}
