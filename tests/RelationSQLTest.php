<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class RelationSQLTest extends SQLTestCase
{

    public function testBasic()
    {
        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John'],
                2=>['id'=>2, 'name'=>'Peter'],
                3=>['id'=>3, 'name'=>'Joe'],
            ], 'order'=>[
                ['amount'=>'20', 'user_id'=>1],
                ['amount'=>'15', 'user_id'=>2],
                ['amount'=>'5', 'user_id'=>1],
                ['amount'=>'3', 'user_id'=>1],
                ['amount'=>'8', 'user_id'=>3],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $u = (new Model($db, 'user'))->addFields(['name']);
        $o = (new Model($db, 'order'))->addFields(['amount','user_id']);

        $u->hasMany('Orders', $o);

        $oo = $u->load(1)->ref('Orders');
        $oo->tryLoad(1); $this->assertEquals(20, $oo['amount']);
        $oo->tryLoad(2); $this->assertEquals(null, $oo['amount']);
        $oo->tryLoad(3); $this->assertEquals(5, $oo['amount']);

        $oo = $u->load(2)->ref('Orders');
        $oo->tryLoad(1); $this->assertEquals(null, $oo['amount']);
        $oo->tryLoad(2); $this->assertEquals(15, $oo['amount']);
        $oo->tryLoad(3); $this->assertEquals(null, $oo['amount']);
    }

}
