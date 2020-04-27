<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

class Model_Rate extends \atk4\data\Model
{
    public $table = 'rate';

    public function init(): void
    {
        parent::init();
        $this->addField('dat');
        $this->addField('bid');
        $this->addField('ask');
    }
}
class Model_Item extends \atk4\data\Model
{
    public $table = 'item';

    public function init(): void
    {
        parent::init();
        $this->addField('name');
        $this->hasOne('parent_item_id', self::class)
            ->addTitle();
    }
}
class Model_Item2 extends \atk4\data\Model
{
    public $table = 'item';

    public function init(): void
    {
        parent::init();
        $this->addField('name');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', new self())
            ->addTitle();
    }
}
class Model_Item3 extends \atk4\data\Model
{
    public $table = 'item';

    public function init(): void
    {
        parent::init();

        $m = new self();

        $this->addField('name');
        $this->addField('age');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', [$m, 'table_alias' => 'parent'])
            ->withTitle();

        $this->hasMany('Child', [$m, 'their_field' => 'parent_item_id', 'table_alias' => 'child'])
            ->addField('child_age', ['aggregate' => 'sum', 'field' => 'age']);
    }
}

/**
 * @coversDefaultClass \atk4\data\Model
 */
class RandomTest extends \atk4\schema\PhpunitTestCase
{
    public function testRate()
    {
        $a = [
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ], ];
        $this->setDB($a);

        $db = new Persistence\SQL($this->db->connection);
        $m = new Model_Rate($db);

        $this->assertEquals(2, $m->action('count')->getOne());
    }

    public function testTitleImport()
    {
        $a = [
            'user' => [
                '_' => ['name' => 'John', 'salary' => 29],
            ], ];
        $this->setDB($a);

        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name', ['salary', 'default' => 10]]);

        $m->import(['Peter', ['Steve', 'salary' => 30]]);
        $m->insert('Sue');
        $m->insert(['John', 'salary' => 40]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'Peter', 'salary' => 10],
                2 => ['id' => 2, 'name' => 'Steve', 'salary' => 30],
                3 => ['id' => 3, 'name' => 'Sue', 'salary' => 10],
                4 => ['id' => 4, 'name' => 'John', 'salary' => 40],
            ], ], $this->getDB());
    }

    public function testAddFields()
    {
        if ($this->driverType == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'user' => [
                1 => ['name' => 'John', 'login' => 'john@example.com'],
            ], ];
        $this->setDB($a);

        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name', 'login'], ['default' => 'unknown']);

        $m->insert(['name' => 'Peter']);
        $m->insert([]);

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'login' => 'john@example.com'],
                2 => ['id' => 2, 'name' => 'Peter', 'login' => 'unknown'],
                3 => ['id' => 3, 'name' => 'unknown', 'login' => 'unknown'],
            ], ], $this->getDB());
    }

    public function testAddFields2()
    {
        if ($this->driverType == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'user' => [
                1 => ['name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ], ];
        $this->setDB($a);

        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name'], ['default' => 'anonymous']);
        $m->addFields([
            'last_name',
            'login' => ['default' => 'unknown'],
            'salary' => ['type' => 'money', CustomField::class, 'default' => 100],
            ['tax', CustomField::class, 'type' => 'money', 'default' => 20],
            'vat' => new CustomField(['type' => 'money', 'default' => 15]),
        ]);

        $m->insert([]);

        $this->assertEquals([
            ['id' => 1, 'name' => 'John', 'last_name' => null, 'login' => null, 'salary' => null, 'tax' => null, 'vat' => null],
            ['id' => 2, 'name' => 'anonymous', 'last_name' => null, 'login' => 'unknown', 'salary' => 100, 'tax' => 20, 'vat' => 15],
        ], $m->export());

        $m->load(2);
        $this->assertTrue(is_float($m->get('salary')));
        $this->assertTrue(is_float($m->get('tax')));
        $this->assertTrue(is_float($m->get('vat')));
    }

    public function testSameTable()
    {
        if ($this->driverType == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => '1'],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => '1'],
                3 => ['id' => 3, 'name' => 'Smith', 'parent_item_id' => '2'],
            ], ];
        $this->setDB($a);

        $m = new Model_Item($db, 'item');

        $this->assertEquals(
            ['id' => '3', 'name' => 'Smith', 'parent_item_id' => '2', 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable2()
    {
        if ($this->driverType == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Sue'],
                3 => ['id' => 3, 'name' => 'Smith'],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => '1'],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => '1'],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => '2'],
            ],
        ];
        $this->setDB($a);

        $m = new Model_Item2($db, 'item');

        $this->assertEquals(
            ['id' => '3', 'name' => 'Smith', 'parent_item_id' => '2', 'parent_item' => 'Sue'],
            $m->load(3)->get()
        );
    }

    public function testSameTable3()
    {
        if ($this->driverType == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'age' => 18],
                2 => ['id' => 2, 'name' => 'Sue', 'age' => 20],
                3 => ['id' => 3, 'name' => 'Smith', 'age' => 24],
            ],
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => '1'],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => '1'],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => '2'],
            ],
        ];
        $this->setDB($a);

        $m = new Model_Item3($db, 'item');

        $this->assertEquals(
            ['id' => '2', 'name' => 'Sue', 'parent_item_id' => '1', 'parent_item' => 'John', 'age' => '20', 'child_age' => 24],
            $m->load(2)->get()
        );

        $this->assertEquals(1, $m->load(2)->ref('Child', ['table_alias' => 'pp'])->action('count')->getOne());
        $this->assertEquals('John', $m->load(2)->ref('parent_item_id', ['table_alias' => 'pp'])->get('name'));
    }

    public function testUpdateCondition()
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

        $m->onHook('afterUpdateQuery', function ($m, $update, $st) {
            // we can use afterUpdate to make sure that record was updated

            if (!$st->rowCount()) {
                throw new \atk4\core\Exception([
                    'Update didn\'t affect any records',
                    'query' => $update->getDebugQuery(false),
                    'statement' => $st,
                    'model' => $m,
                    'conditions' => $m->conditions,
                ]);
            }
        });

        $this->assertEquals('Sue', $m['name']);

        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John'],
            ], ];
        $this->setDB($a);

        $m['name'] = 'Peter';

        try {
            $m->save();
            $e = null;
        } catch (\Exception $e) {
        }

        $this->assertNotNull($e);
        $this->assertEquals($a, $this->getDB());
    }

    public function testHookBreakers()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('name');

        $m->onHook('beforeSave', function ($m) {
            $m->breakHook(false);
        });

        $m->onHook('beforeLoad', function ($m, $id) {
            $m->data = ['name' => 'rec #' . $id];
            $m->id = $id;
            $m->breakHook(false);
        });

        $m->onHook('beforeDelete', function ($m, $id) {
            $m->unload();
            $m->breakHook(false);
        });

        $m->set('john');
        $m->save();

        $this->assertEquals('rec #3', $m->load(3)['name']);

        $m->delete();
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testIssue220()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model_Item($db);

        $m->hasOne('foo', Model_Item::class)
            ->addTitle(); // field foo already exists, so we can't add title with same name
    }

    public function testIssue163()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model_Item($db);

        $m->hasOne('Person', 'atk4/data/tests/Model/Person');
        $person = $m->ref('Person');
    }

    public function testNonSQLFieldClass()
    {
        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4, 'x1' => 'y1', 'x2' => 'y2'],
            ],
        ];
        $this->setDB($a);

        $m = new Model_Rate($db);
        $m->addField('x1', new \atk4\data\Field_SQL());
        $m->addField('x2', new \atk4\data\Field());
        $m->load(1);

        $this->assertEquals(3.4, $m['bid']);
        $this->assertEquals('y1', $m['x1']);
        $this->assertEquals('y2', $m['x2']);
    }

    public function testModelCaption()
    {
        $db = new Persistence\SQL($this->db->connection);
        $m = new Model($db, 'user');

        // caption is not set, so generate it from class name \atk4\data\Model
        $this->assertEquals('Atk 4 Data Model', $m->getModelCaption());

        // caption is set
        $m->caption = 'test';
        $this->assertEquals('test', $m->getModelCaption());
    }

    public function testGetTitle()
    {
        if ($this->driverType == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $db = new Persistence\SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => '1'],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => '1'],
            ], ];
        $this->setDB($a);

        $m = new Model_Item($db, 'item');

        // default title_field = name
        $this->assertNull($m->getTitle()); // not loaded model returns null
        $this->assertEquals([1 => 'John', 2 => 'Sue'], $m->getTitles()); // all titles

        $m->load(2);
        $this->assertEquals('Sue', $m->getTitle()); // loaded returns title_field value
        $this->assertEquals([1 => 'John', 2 => 'Sue'], $m->getTitles()); // all titles

        // set custom title_field
        $m->title_field = 'parent_item_id';
        $this->assertEquals(1, $m->getTitle()); // returns parent_item_id value

        // set custom title_field as title_field from linked model
        $m->title_field = 'parent_item';
        $this->assertEquals('John', $m->getTitle()); // returns parent record title_field

        // no title_field set - return id value
        $m->title_field = null;
        $this->assertEquals(2, $m->getTitle()); // loaded returns id value

        // expression as title field
        $m->addExpression('my_name', '[id]');
        $m->title_field = 'my_name';
        $m->load(2);
        $this->assertEquals(2, $m->getTitle()); // loaded returns id value
        $this->assertEquals([1 => 1, 2 => 2], $m->getTitles()); // all titles (my_name)
    }

    /**
     * Test export.
     */
    public function testExport()
    {
        $a = [
            'user' => [
                2 => ['code' => 10, 'name' => 'John'],
                5 => ['code' => 20, 'name' => 'Sarah'],
            ], ];
        $this->setDB($a);

        // model without id field
        $m1 = new Model($this->db, ['table' => 'user', 'id_field' => false]);
        $m1->addField('code');
        $m1->addField('name');

        // model with id field
        $m2 = new Model($this->db, 'user');
        $m2->addField('code');
        $m2->addField('name');

        // normal export
        $this->assertEquals([
            0 => ['code' => 10, 'name' => 'John'],
            1 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export());

        $this->assertEquals([
            0 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            1 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export());

        // export fields explicitly set
        $this->assertEquals([
            0 => ['name' => 'John'],
            1 => ['name' => 'Sarah'],
        ], $m1->export(['name']));

        $this->assertEquals([
            0 => ['name' => 'John'],
            1 => ['name' => 'Sarah'],
        ], $m2->export(['name']));

        // key field explicitly set
        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(null, 'code'));

        $this->assertEquals([
            10 => ['id' => 2, 'code' => 10, 'name' => 'John'],
            20 => ['id' => 5, 'code' => 20, 'name' => 'Sarah'],
        ], $m2->export(null, 'code'));

        // field names and key field explicitly set
        $this->assertEquals([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m1->export(['name'], 'code'));

        $this->assertEquals([
            10 => ['name' => 'John'],
            20 => ['name' => 'Sarah'],
        ], $m2->export(['name'], 'code'));

        // field names include key field
        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m1->export(['code', 'name'], 'code'));

        $this->assertEquals([
            10 => ['code' => 10, 'name' => 'John'],
            20 => ['code' => 20, 'name' => 'Sarah'],
        ], $m2->export(['code', 'name'], 'code'));
    }

    public function testNewInstance()
    {
        // model without persistence
        $m = new Model(['table' => 'order']);
        $a = $m->newInstance();
        $this->assertFalse(isset($a->persistence));

        // model with persistence
        $db = new Persistence();
        $m = new Model($db, ['table' => 'order']);
        $a = $m->newInstance();
        $this->assertTrue(isset($a->persistence));
    }

    public function testTableNameDots()
    {
        $d = new Model($this->db, 'db2.doc');
        $d->addField('name');

        $m = new Model($this->db, 'db1.user');
        $m->addField('name');

        $d->hasOne('user_id', $m)->addTitle();
        $m->hasMany('Documents', $d);

        $d->addCondition('user', 'Sarah');

        $q = 'select "id","name","user_id",(select "name" from "db1"."user" where "id" = "db2"."doc"."user_id") "user" from "db2"."doc" where (select "name" from "db1"."user" where "id" = "db2"."doc"."user_id") = :a';
        $q = str_replace('"', $this->getEscapeChar(), $q);
        $this->assertEquals(
            $q,
            $d->action('select')->render()
        );
    }
}

class CustomField extends \atk4\data\Field
{
}
