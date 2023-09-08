<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Exception as CoreException;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\SQLitePlatform;

class Model_Rate extends Model
{
    public $table = 'rate';

    protected function init(): void
    {
        parent::init();

        $this->addField('dat');
        $this->addField('bid', ['type' => 'float']);
        $this->addField('ask', ['type' => 'float']);
    }
}
class Model_Item extends Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->hasOne('parent_item_id', ['model' => [self::class]])
            ->addTitle();
    }
}
class Model_Item2 extends Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', ['model' => [self::class]])
            ->addTitle();
    }
}
class Model_Item3 extends Model
{
    public $table = 'item';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');
        $this->addField('age', ['type' => 'integer']);
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', ['model' => [self::class], 'tableAlias' => 'parent'])
            ->addTitle();

        $this->hasMany('Child', ['model' => [self::class], 'theirField' => 'parent_item_id', 'tableAlias' => 'child'])
            ->addField('child_age', ['type' => 'integer', 'aggregate' => 'sum', 'field' => 'age']);
    }
}

class RandomTest extends TestCase
{
    public function testRate(): void
    {
        $this->setDb([
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ],
        ]);

        $m = new Model_Rate($this->db);

        self::assertSame(2, $m->executeCountQuery());
    }

    public function testTitleImport(): void
    {
        $this->setDb([
            'user' => [
                '_' => ['name' => 'John', 'salary' => 29],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('salary', ['default' => 10]);

        $m->import([['name' => 'Peter'], ['name' => 'Steve', 'salary' => 30]]);
        $m->insert(['name' => 'Sue']);
        $m->insert(['name' => 'John', 'salary' => 40]);

        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'Peter', 'salary' => '10'],
                ['id' => 2, 'name' => 'Steve', 'salary' => '30'],
                ['id' => 3, 'name' => 'Sue', 'salary' => '10'],
                ['id' => 4, 'name' => 'John', 'salary' => '40'],
            ],
        ], $this->getDb());
    }

    public function testAddFields(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'login' => 'john@example.com'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'login'], ['default' => 'unknown']);

        $m->insert(['name' => 'Peter']);
        $m->insert([]);

        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'login' => 'john@example.com'],
                ['id' => 2, 'name' => 'Peter', 'login' => 'unknown'],
                ['id' => 3, 'name' => 'unknown', 'login' => 'unknown'],
            ],
        ], $this->getDb());
    }

    public function testAddFields2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name'], ['default' => 'anonymous']);
        $m->addFields([
            'last_name',
            'login' => ['default' => 'unknown'],
            'salary' => [CustomField::class, 'type' => 'atk4_money', 'default' => 100],
            'tax' => [CustomField::class, 'type' => 'atk4_money', 'default' => 20],
            'vat' => new CustomField(['type' => 'atk4_money', 'default' => 15]),
        ]);

        $m->insert([]);

        self::assertSameExportUnordered([
            ['id' => 1, 'name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ['id' => 2, 'name' => 'anonymous', 'last_name' => null, 'login' => 'unknown', 'salary' => 100.0, 'tax' => 20.0, 'vat' => 15.0],
        ], $m->export());

        $m = $m->load(2);
        self::assertTrue(is_float($m->get('salary')));
        self::assertTrue(is_float($m->get('tax')));
        self::assertTrue(is_float($m->get('vat')));
        self::assertInstanceOf(CustomField::class, $m->getField('salary'));
    }

    public function testSetPersistence(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        self::assertTrue($m->hasField('id'));

        $m = new Model(null, ['table' => 'user']);
        self::assertFalse($m->hasField('id'));

        $p = new Persistence\Array_();
        $pAddCalled = false;
        $p->onHookShort(Persistence::HOOK_AFTER_ADD, static function (Model $mFromHook) use ($m, &$pAddCalled) {
            self::assertSame($m, $mFromHook);
            $pAddCalled = true;
        });

        $m->setPersistence($p);
        self::assertTrue($m->hasField('id'));
        self::assertTrue($pAddCalled);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Persistence is already set');
        $m->setPersistence($p);
    }

    public function testPersistenceAddException(): void
    {
        $m = new Model(null, ['table' => 'user']);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Persistence::add() cannot be called directly');
        $this->db->add($m);
    }

    public function testSameTable(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
                ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item($this->db, ['table' => 'item']);

        self::assertSame(
            ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2, 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable2(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Sue'],
                ['id' => 3, 'name' => 'Smith'],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item2($this->db, ['table' => 'item']);

        self::assertSame(
            ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2, 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable3(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'age' => 18],
                ['id' => 2, 'name' => 'Sue', 'age' => 20],
                ['id' => 3, 'name' => 'Smith', 'age' => 24],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item3($this->db, ['table' => 'item']);

        self::assertSame(
            ['id' => 2, 'name' => 'Sue', 'age' => 20, 'parent_item_id' => 1, 'parent_item' => 'John', 'child_age' => 24],
            $m->load(2)->get()
        );

        self::assertSame(1, $m->load(2)->ref('Child', ['tableAlias' => 'pp'])->executeCountQuery());
        self::assertSame('John', $m->load(2)->ref('parent_item_id', ['tableAlias' => 'pp'])->get('name'));
    }

    public function testDirty2(): void
    {
        $p = new Persistence\Static_([1 => 'hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->addExpression('caps', ['expr' => static function (Model $m) {
            return strtoupper($m->get('name'));
        }]);

        $m = $m->load(2);
        self::assertSame('world', $m->get('name'));
        self::assertSame('WORLD', $m->get('caps'));
    }

    public function testHookBreakers(): void
    {
        $this->setDb([
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'never_used']);
        $m->addField('name');

        $m->onHook(Model::HOOK_BEFORE_SAVE, static function (Model $m) {
            $m->breakHook(false);
        });

        $m->onHook(Model::HOOK_BEFORE_LOAD, static function (Model $m, int $id) {
            $m->setId($id);
            $m->set('name', 'rec #' . $id);

            $m->breakHook($m);
        });

        $m->onHook(Model::HOOK_BEFORE_DELETE, static function (Model $m) {
            $m->unload();

            $m->breakHook(false);
        });

        $m = $m->createEntity();
        $m->set('name', 'john');
        $m->save();

        $m = $m->getModel()->load(3);
        self::assertSame('rec #3', $m->get('name'));

        $m->delete();
    }

    public function testIssue220(): void
    {
        $m = new Model_Item($this->db);

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('already exist');
        $m->hasOne('foo', ['model' => [Model_Item::class]])->addTitle();
    }

    public function testModelCaption(): void
    {
        // caption is not set, so generate it from class name Model
        $m = new Model($this->db, ['table' => 'user']);
        self::assertSame('Atk 4 Data Model', $m->getModelCaption());

        $m = new class($this->db, ['table' => 'user']) extends Model {};
        self::assertSame('Atk 4 Data Model Anonymous', $m->getModelCaption());

        // caption is set
        $m->caption = 'test';
        self::assertSame('test', $m->getModelCaption());
    }

    public function testGetTitle(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
            ],
        ]);

        $m = new Model_Item($this->db, ['table' => 'item']);

        self::assertSame([1 => 'John', 'Sue'], $m->setOrder('id')->getTitles()); // all titles

        $mm = $m->createEntity();

        // default titleField = name
        self::assertNull($mm->getTitle()); // not loaded model returns null

        $mm = $m->load(2);
        self::assertSame('Sue', $mm->getTitle()); // loaded returns titleField value

        // set custom titleField
        $m->titleField = 'parent_item_id';
        self::assertSame(1, $mm->getTitle()); // returns parent_item_id value

        // set custom titleField as titleField from linked model
        $m->titleField = 'parent_item';
        self::assertSame('John', $mm->getTitle()); // returns parent record titleField

        // no titleField set - return id value
        $m->titleField = null;
        self::assertSame(2, $mm->getTitle()); // loaded returns id value

        // expression as title field
        $m->addExpression('my_name', ['expr' => '[id]']);
        $m->titleField = 'my_name';
        $mm = $m->load(2);
        self::assertSame('2', $mm->getTitle()); // loaded returns id value

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected model, but instance is an entity');
        $mm->getTitles();
    }

    public function testExport(): void
    {
        $this->setDb([
            'user' => [
                2 => ['code' => 10, 'name' => 'John'],
                5 => ['code' => 20, 'name' => 'Sarah'],
            ],
        ]);

        // model without id field
        $m1 = new Model($this->db, ['table' => 'user', 'idField' => false]);
        $m1->addField('code', ['type' => 'integer']);
        $m1->addField('name');

        // model with id field
        $m2 = new Model($this->db, ['table' => 'user']);
        $m2->addField('code', ['type' => 'integer']);
        $m2->addField('name');

        // normal export
        self::assertSameExportUnordered([
            ['code' => 10, 'name' => 'John'],
            ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export());

        self::assertSameExportUnordered([
            ['id' => 2, 'code' => 10, 'name' => 'John'],
            ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export());

        // export fields explicitly set
        self::assertSameExportUnordered([
            ['name' => 'John'],
            ['name' => 'Sarah'],
        ], $m1->export(['name']));

        self::assertSameExportUnordered([
            ['name' => 'John'],
            ['name' => 'Sarah'],
        ], $m2->export(['name']));

        // key field explicitly set
        self::assertSameExportUnordered([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(null, 'code'));

        self::assertSameExportUnordered([
            10 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            20 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export(null, 'code'));

        // field names and key field explicitly set
        self::assertSameExportUnordered([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m1->export(['name'], 'code'));

        self::assertSameExportUnordered([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m2->export(['name'], 'code'));

        // field names include key field
        self::assertSameExportUnordered([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(['code', 'name'], 'code'));

        self::assertSameExportUnordered([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m2->export(['code', 'name'], 'code'));
    }

    public function testDuplicateSaveNew(): void
    {
        $this->setDb([
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ],
        ]);

        $m = new Model_Rate($this->db);

        $m->load(1)->duplicate()->save();

        self::assertSameExportUnordered([
            ['id' => 1, 'dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
            ['id' => 2, 'dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ['id' => 3, 'dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
        ], $m->export());
    }

    public function testNoWriteActionInsert(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported action mode');
        $this->db->action(new Model(), 'insert');
    }

    public function testNoWriteActionUpdate(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported action mode');
        $this->db->action(new Model(), 'update');
    }

    public function testNoWriteActionDelete(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unsupported action mode');
        $this->db->action(new Model(), 'delete');
    }

    public function testTableWithSchema(): void
    {
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            $userSchema = 'db1';
            $docSchema = 'db2';
            $runWithDb = false;
        } else {
            $dbSchema = $this->getConnection()->dsql()
                ->field($this->getConnection()->expr('{{}}', [$this->getDatabasePlatform()->getCurrentDatabaseExpression(true)])) // @phpstan-ignore-line
                ->getOne();
            $userSchema = $dbSchema;
            $docSchema = $dbSchema;
            $runWithDb = true;
        }

        $user = new Model($this->db, ['table' => $userSchema . '.user']);
        $user->addField('name');

        $doc = new Model($this->db, ['table' => $docSchema . '.doc']);
        $doc->addField('name');
        $doc->hasOne('user_id', ['model' => $user])->addTitle();
        $doc->addCondition('user', 'Sarah');
        $user->hasMany('Documents', ['model' => $doc]);

        // render twice, render must be stable
        $selectAction = $doc->action('select');
        $render = $selectAction->render();
        self::assertSame($render, $selectAction->render());
        self::assertSame($render, $doc->action('select')->render());

        $userTableQuoted = '`' . str_replace('.', '`.`', $userSchema) . '`.`user`';
        $docTableQuoted = '`' . str_replace('.', '`.`', $docSchema) . '`.`doc`';
        $this->assertSameSql(
            'select `id`, `name`, `user_id`, (select `name` from ' . $userTableQuoted . ' `_u_e8701ad48ba0` where `id` = ' . $docTableQuoted . '.`user_id`) `user` from ' . $docTableQuoted . ' where (select `name` from ' . $userTableQuoted . ' `_u_e8701ad48ba0` where `id` = ' . $docTableQuoted . '.`user_id`) = :a',
            $render[0]
        );

        if ($runWithDb) {
            $this->createMigrator($user)->create();
            $this->createMigrator($doc)->create();
            $this->createMigrator()->createForeignKey($doc->getReference('user_id'));

            $user->createEntity()
                ->set('name', 'Sarah')
                ->save();

            $doc->createEntity()
                ->set('name', 'Invoice 7')
                ->set('user_id', 1)
                ->save();

            self::assertSame([
                [
                    'id' => 1,
                    'name' => 'Invoice 7',
                    'user_id' => 1,
                    'user' => 'Sarah',
                ],
            ], $doc->export());
        }
    }
}

class CustomField extends Field {}
