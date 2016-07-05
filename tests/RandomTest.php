<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;


class Model_Rate extends \atk4\data\Model {
    public $table = "rate";
    function init(){
        parent::init();
        $this->addField("dat");
        $this->addField("bid");
        $this->addField("ask");
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
            'rate'=>[
                ['dat'=>'18/12/12', 'bid'=>3.4, 'ask'=>9.4],
                ['dat'=>'12/12/12', 'bid'=>8.3, 'ask'=>9.2]
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model_Rate($db);

        $this->assertEquals(2, $m->action('count')->getOne());

    }


    public function testBasic()
    {
        $this->markTestIncomplete(
          'This test has not been implemented yet.'
        );
        

        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John', 'gender'=>'M'],
                2=>['id'=>2, 'name'=>'Sue', 'gender'=>'F'],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);

        $clients = new Model_Client($db);
        // Object representing all clients - DataSet

        $clients -> addCondition('is_vip', true);
        // Now DataSet is limited to VIP clients only

        $vip_client_orders = $clients->ref('Order');
        // This DataSet will contain only orders placed by VIP clients

        $vip_client_orders->addExpression('item_price')->set(function($model, $query){
            return $model->ref('item_id')->fieldQuery('price');
        });
        // Defines a new field for a model expressed through relation with Item

        $vip_client_orders->addExpression('paid')->set(function($model, $query){
            return $model->ref('Payment')->sum('amount');
        });
        // Defines another field as sum of related payments

        $vip_client_orders->addExpression('due')->set(function($model, $query){
            return $query->expr('{item_price} * {qty} - {paid}');
        });
        // Defines third field for calculating due

        $total_due_payment = $vip_client_orders->sum('due')->getOne();
        // Defines and executes "sum" action on our expression across specified data-set


        $m = new Model($db, 'user');
        $m->addFields(['name','gender']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $m->addCondition('gender','M');
        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals(null, $m['name']);

        $this->assertEquals(
            'select `id`,`name`,`gender` from `user` where `gender` = :a',
            $m->action('select')->render()
        );
    }
}
