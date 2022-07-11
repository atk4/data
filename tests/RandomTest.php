<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Exception as CoreException;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\SqlitePlatform;

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

        $m = new self();

        $this->addField('name');
        $this->addField('age');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', ['model' => $m, 'table_alias' => 'parent'])
            ->addTitle();

        $this->hasMany('Child', ['model' => $m, 'their_field' => 'parent_item_id', 'table_alias' => 'child'])
            ->addField('child_age', ['aggregate' => 'sum', 'field' => 'age']);
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

        $this->assertSame(2, $m->executeCountQuery());
    }

    public function testTitleImport(): void
    {
        $this->setDb([
            'user' => [
                '_' => ['name' => 'John', 'salary' => 29],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'salary' => ['default' => 10]]);

        $m->import([['name' => 'Peter'], ['name' => 'Steve', 'salary' => 30]]);
        $m->insert(['name' => 'Sue']);
        $m->insert(['name' => 'John', 'salary' => 40]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'Peter', 'salary' => 10],
                2 => ['id' => 2, 'name' => 'Steve', 'salary' => 30],
                3 => ['id' => 3, 'name' => 'Sue', 'salary' => 10],
                4 => ['id' => 4, 'name' => 'John', 'salary' => 40],
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

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'login' => 'john@example.com'],
                2 => ['id' => 2, 'name' => 'Peter', 'login' => 'unknown'],
                3 => ['id' => 3, 'name' => 'unknown', 'login' => 'unknown'],
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

        $this->assertSameExportUnordered([
            ['id' => 1, 'name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ['id' => 2, 'name' => 'anonymous', 'last_name' => null, 'login' => 'unknown', 'salary' => 100.0, 'tax' => 20.0, 'vat' => 15.0],
        ], $m->export());

        $m = $m->load(2);
        $this->assertTrue(is_float($m->get('salary')));
        $this->assertTrue(is_float($m->get('tax')));
        $this->assertTrue(is_float($m->get('vat')));
        $this->assertInstanceOf(CustomField::class, $m->getField('salary'));
    }

    public function testSetPersistence(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $this->assertTrue($m->hasField('id'));

        $m = new Model(null, ['table' => 'user']);
        $this->assertFalse($m->hasField('id'));

        $p = new Persistence\Array_();
        $pAddCalled = false;
        $p->onHookShort(Persistence::HOOK_AFTER_ADD, function (Model $mFromHook) use ($m, &$pAddCalled) {
            $this->assertSame($m, $mFromHook);
            $pAddCalled = true;
        });

        $m->setPersistence($p);
        $this->assertTrue($m->hasField('id'));
        $this->assertTrue($pAddCalled);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Persistence already set');
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
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
                3 => ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item($this->db, ['table' => 'item']);

        $this->assertSame(
            ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2, 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable2(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Sue'],
                3 => ['id' => 3, 'name' => 'Smith'],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item2($this->db, ['table' => 'item']);

        $this->assertSame(
            ['id' => 3, 'name' => 'Smith', 'parent_item_id' => 2, 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable3(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'age' => 18],
                2 => ['id' => 2, 'name' => 'Sue', 'age' => 20],
                3 => ['id' => 3, 'name' => 'Smith', 'age' => 24],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => 1],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => 1],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => 2],
            ],
        ]);

        $m = new Model_Item3($this->db, ['table' => 'item']);

        $this->assertEquals(
            ['id' => '2', 'name' => 'Sue', 'parent_item_id' => 1, 'parent_item' => 'John', 'age' => '20', 'child_age' => 24],
            $m->load(2)->get()
        );

        $this->assertSame(1, $m->load(2)->ref('Child', ['table_alias' => 'pp'])->executeCountQuery());
        $this->assertSame('John', $m->load(2)->ref('parent_item_id', ['table_alias' => 'pp'])->get('name'));
    }

    public function testDirty2(): void
    {
        $p = new Persistence\Static_([1 => 'hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->addExpression('caps', ['expr' => function ($m) {
            return strtoupper($m->get('name'));
        }]);

        $m = $m->load(2);
        $this->assertSame('world', $m->get('name'));
        $this->assertSame('WORLD', $m->get('caps'));
    }

    public function testUpdateCondition(): void
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

        $m->onHook(Persistence\Sql::HOOK_AFTER_UPDATE_QUERY, static function (Model $m, Query $update, int $c) {
            // we can use afterUpdate to make sure that record was updated
            if ($c === 0) {
                throw (new Exception('Update didn\'t affect any records'))
                    ->addMoreInfo('query', $update->getDebugQuery())
                    ->addMoreInfo('affected_rows', $c)
                    ->addMoreInfo('model', $m);
            }
        });

        $this->assertSame('Sue', $m->get('name'));

        $dbData = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
        ];
        $this->setDb($dbData);

        $m->set('name', 'Peter');

        try {
            $this->expectException(Exception::class);

            $m->save();
        } catch (\Exception $e) {
            $this->assertEquals($dbData, $this->getDb());

            throw $e;
        }
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
        $this->assertSame('rec #3', $m->get('name'));

        $m->delete();
    }

    public function testIssue220(): void
    {
        $m = new Model_Item($this->db);

        $this->expectException(CoreException::class);
        $m->hasOne('foo', ['model' => [Model_Item::class]])
            ->addTitle(); // field foo already exists, so we can't add title with same name
    }

    public function testModelCaption(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        // caption is not set, so generate it from class name Model
        $this->assertSame('Atk 4 Data Model', $m->getModelCaption());

        // caption is set
        $m->caption = 'test';
        $this->assertSame('test', $m->getModelCaption());
    }

    public function testGetTitle(): void
    {
        $this->setDb([
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => 1],
            ],
        ]);

        $m = new Model_Item($this->db, ['table' => 'item']);

        $this->assertSame([1 => 'John', 2 => 'Sue'], $m->setOrder('id')->getTitles()); // all titles

        $mm = $m->createEntity();

        // default title_field = name
        $this->assertNull($mm->getTitle()); // not loaded model returns null

        $mm = $m->load(2);
        $this->assertSame('Sue', $mm->getTitle()); // loaded returns title_field value

        // set custom title_field
        $m->title_field = 'parent_item_id';
        $this->assertEquals(1, $mm->getTitle()); // returns parent_item_id value

        // set custom title_field as title_field from linked model
        $m->title_field = 'parent_item';
        $this->assertSame('John', $mm->getTitle()); // returns parent record title_field

        // no title_field set - return id value
        $m->title_field = null;
        $this->assertEquals(2, $mm->getTitle()); // loaded returns id value

        // expression as title field
        $m->addExpression('my_name', ['expr' => '[id]']);
        $m->title_field = 'my_name';
        $mm = $m->load(2);
        $this->assertEquals(2, $mm->getTitle()); // loaded returns id value

        $this->expectException(Exception::class);
        $mm->getTitles();
    }

    /**
     * Test export.
     */
    public function testExport(): void
    {
        $this->setDb([
            'user' => [
                2 => ['code' => 10, 'name' => 'John'],
                5 => ['code' => 20, 'name' => 'Sarah'],
            ],
        ]);

        // model without id field
        $m1 = new Model($this->db, ['table' => 'user', 'id_field' => false]);
        $m1->addField('code', ['type' => 'integer']);
        $m1->addField('name');

        // model with id field
        $m2 = new Model($this->db, ['table' => 'user']);
        $m2->addField('code', ['type' => 'integer']);
        $m2->addField('name');

        // normal export
        $this->assertSameExportUnordered([
            ['code' => 10, 'name' => 'John'],
            ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export());

        $this->assertSameExportUnordered([
            ['id' => 2, 'code' => 10, 'name' => 'John'],
            ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export());

        // export fields explicitly set
        $this->assertSameExportUnordered([
            ['name' => 'John'],
            ['name' => 'Sarah'],
        ], $m1->export(['name']));

        $this->assertSameExportUnordered([
            ['name' => 'John'],
            ['name' => 'Sarah'],
        ], $m2->export(['name']));

        // key field explicitly set
        $this->assertSameExportUnordered([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(null, 'code'));

        $this->assertSameExportUnordered([
            10 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            20 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export(null, 'code'));

        // field names and key field explicitly set
        $this->assertSameExportUnordered([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m1->export(['name'], 'code'));

        $this->assertSameExportUnordered([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m2->export(['name'], 'code'));

        // field names include key field
        $this->assertSameExportUnordered([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(['code', 'name'], 'code'));

        $this->assertSameExportUnordered([
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

        $this->assertSameExportUnordered([
            ['id' => 1, 'dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
            ['id' => 2, 'dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ['id' => 3, 'dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
        ], $m->export());
    }

    public function testDuplicateWithIdArgumentException(): void
    {
        $m = new Model_Rate();
        $this->expectException(Exception::class);
        $m->duplicate(2)->save();
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
        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $userSchema = 'db1';
            $docSchema = 'db2';
            $runWithDb = false;
        } else {
            $dbSchema = $this->db->getConnection()->dsql()
                ->field(new Expression($this->getDatabasePlatform()->getCurrentDatabaseExpression(true)))
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
        $this->assertSame($render, $selectAction->render());
        $this->assertSame($render, $doc->action('select')->render());

        $userTableQuoted = '"' . str_replace('.', '"."', $userSchema) . '"."user"';
        $docTableQuoted = '"' . str_replace('.', '"."', $docSchema) . '"."doc"';
        $this->assertSameSql(
            'select "id", "name", "user_id", (select "name" from ' . $userTableQuoted . ' "_u_e8701ad48ba0" where "id" = ' . $docTableQuoted . '."user_id") "user" from ' . $docTableQuoted . ' where (select "name" from ' . $userTableQuoted . ' "_u_e8701ad48ba0" where "id" = ' . $docTableQuoted . '."user_id") = :a',
            $render[0]
        );

        if ($runWithDb) {
            $this->createMigrator($user)->create();
            $this->createMigrator($doc)->create();

            $user->createEntity()
                ->set('name', 'Sarah')
                ->save();

            $doc->createEntity()
                ->set('name', 'Invoice 7')
                ->set('user_id', 1)
                ->save();

            $this->assertSame([
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

class CustomField extends Field
{
}
