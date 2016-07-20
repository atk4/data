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
class Model_Item extends \atk4\data\Model {
    public $table='item';
    function init() {
        parent::init();
        $this->addField('name');
        $this->hasOne('parent_item_id', '\atk4\data\tests\Item')
            ->addTitle();
    }
}
class Model_Item2 extends \atk4\data\Model {
    public $table='item';
    function init() {
        parent::init();
        $this->addField('name');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', new Model_Item2())
            ->addTitle();
    }
}
class Model_Item3 extends \atk4\data\Model {
    public $table='item';
    function init() {
        parent::init();

        $m = new Model_Item3();

        $this->addField('name');
        $this->addField('age');
        $i2 = $this->join('item2.item_id');
        $i2->hasOne('parent_item_id', $m)
            ->addTitle();

        $this->hasMany('Child', [$m, 'their_field'=>'parent_item_id'])
            ->addField('child_age',['aggregate'=>'sum', 'field'=>'age']);
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

    public function testBasic()
    {
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );


        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);

        $clients = new Model_Client($db);
        // Object representing all clients - DataSet

        $clients->addCondition('is_vip', true);
        // Now DataSet is limited to VIP clients only

        $vip_client_orders = $clients->ref('Order');
        // This DataSet will contain only orders placed by VIP clients

        $vip_client_orders->addExpression('item_price')->set(function ($model, $query) {
            return $model->ref('item_id')->fieldQuery('price');
        });
        // Defines a new field for a model expressed through relation with Item

        $vip_client_orders->addExpression('paid')->set(function ($model, $query) {
            return $model->ref('Payment')->sum('amount');
        });
        // Defines another field as sum of related payments

        $vip_client_orders->addExpression('due')->set(function ($model, $query) {
            return $query->expr('{item_price} * {qty} - {paid}');
        });
        // Defines third field for calculating due

        $total_due_payment = $vip_client_orders->sum('due')->getOne();
        // Defines and executes "sum" action on our expression across specified data-set


        $m = new Model($db, 'user');
        $m->addFields(['name', 'gender']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $m->addCondition('gender', 'M');
        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals(null, $m['name']);

        $this->assertEquals(
            'select `id`,`name`,`gender` from `user` where `gender` = :a',
            $m->action('select')->render()
        );
    }

    function testSameTable()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'parent_item_id' => '1'],
                2 => ['id' => 2, 'name' => 'Sue', 'parent_item_id' => '1'],
                3 => ['id' => 3, 'name' => 'Smith', 'parent_item_id' => '2'],
            ], ];
        $this->setDB($a);

        $m = new Model_Item($db,'item');
            
        $this->assertEquals(
            ['id'=>'3', 'name'=>'Smith', 'parent_item_id'=>'2', 'parent_item'=>'Sue'],
            $m->load(3)->get()
        );

    }

    function testSameTable2()
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

        $m = new Model_Item2($db,'item');
            
        $this->assertEquals(
            ['id'=>'3', 'name'=>'Smith', 'parent_item_id'=>'2', 'parent_item'=>'Sue'],
            $m->load(3)->get()
        );

    }
    function testSameTable3()
    {
        $db = new Persistence_SQL($this->db->connection);
        $a = [
            'item' => [
                1 => ['id' => 1, 'name' => 'John', 'age'=>18],
                2 => ['id' => 2, 'name' => 'Sue', 'age'=>20],
                3 => ['id' => 3, 'name' => 'Smith', 'age'=>24],
            ], 
            'item2' => [
                1 => ['id' => 1, 'item_id' => 1, 'parent_item_id' => '1'],
                2 => ['id' => 2, 'item_id' => 2, 'parent_item_id' => '1'],
                3 => ['id' => 3, 'item_id' => 3, 'parent_item_id' => '2'],
            ], 
        ];
        $this->setDB($a);

        $m = new Model_Item3($db,'item');

        $this->assertEquals(
            ['id'=>'2', 'name'=>'Sue', 'parent_item_id'=>'1', 'parent_item'=>'John', 'age'=>'20', 'child_age'=>24],
            $m->load(2)->get()
        );

    }
}

