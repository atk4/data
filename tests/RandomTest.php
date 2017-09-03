<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

class Model_Rate extends \atk4\data\Model
{
    public $table = 'rate';

    public function init()
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

    public function init()
    {
        parent::init();
        $this->addField('name');
        $this->hasOne('parent_item_id', '\atk4\data\tests\Item')
            ->addTitle();
    }
}
class Model_Item2 extends \atk4\data\Model
{
    public $table = 'item';

    public function init()
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

    public function init()
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
class RandomSQLTests extends SQLTestCase
{
    public function testRate()
    {
        $a = [
            'rate' => [
                ['dat' => '18/12/12', 'bid' => 3.4, 'ask' => 9.4],
                ['dat' => '12/12/12', 'bid' => 8.3, 'ask' => 9.2],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
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

        $db = new Persistence_SQL($this->db->connection);
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
        $a = [
            'user' => [
                1 => ['name' => 'John', 'login' => 'john@example.com'],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
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

    public function testSameTable()
    {
        $db = new Persistence_SQL($this->db->connection);
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
        $db = new Persistence_SQL($this->db->connection);
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
        $db = new Persistence_SQL($this->db->connection);
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
        $db = new Persistence_SQL($this->db->connection);
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

        $m->addHook('afterUpdateQuery', function ($m, $update, $st) {

            // we can use afterUpdate to make sure that record was updated

            if (!$st->rowCount()) {
                throw new \atk4\core\Exception([
                    'Update didn\'t affect any records',
                    'query'      => $update->getDebugQuery(false),
                    'statement'  => $st,
                    'model'      => $m,
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
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'item' => [
                ['name' => 'John'],
                ['name' => 'Sue'],
                ['name' => 'Smith'],
            ], ];
        $this->setDB($a);

        $m = new Model($db, 'user');
        $m->addField('name');

        $m->addHook('beforeSave', function ($m) {
            $m->breakHook(false);
        });

        $m->addHook('beforeLoad', function ($m, $id) {
            $m->data = ['name' => 'rec #'.$id];
            $m->id = $id;
            $m->breakHook(false);
        });

        $m->addHook('beforeDelete', function ($m, $id) {
            $m->unload();
            $m->breakHook(false);
        });

        $m->set('john');
        $m->save();

        $this->assertEquals('rec #3', $m->load(3)['name']);

        $m->delete();
    }

    public function testIssue163()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model_Item($db);

        $m->hasOne('Person', 'atk4/data/tests/Person');
        $person = $m->ref('Person');
    }
}
