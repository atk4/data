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

        $oo = $u->unload()->addCondition('id','>','1')->ref('Orders');
        $this->assertEquals(
            'select `id`,`amount`,`user_id` from `order` where `user_id` in (select `id` from `user` where `id` > :a)',
            $oo->action('select')->render()
        );
    }

    public function testLink()
    {
        $db = new Persistence_SQL($this->db->connection);
        $u = (new Model($db, 'user'))->addFields(['name']);
        $o = (new Model($db, 'order'))->addFields(['amount','user_id']);

        $u->hasMany('Orders', $o);

        $this->assertEquals(
            'select `id`,`amount`,`user_id` from `order` where `user_id` = `user`.`id`',
            $u->refLink('Orders')->action('select')->render()
        );
    }

    public function testBasic2()
    {
        $a = [
            'user'=>[
                ['name'=>'John', 'currency'=>'EUR'],
                ['name'=>'Peter', 'currency'=>'GBP'],
                ['name'=>'Joe', 'currency'=>'EUR'],
            ], 'currency'=>[
                ['currency'=>'EUR', 'name'=>'Euro'],
                ['currency'=>'USD', 'name'=>'Dollar'],
                ['currency'=>'GBP', 'name'=>'Pound'],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $u = (new Model($db, 'user'))->addFields(['name','currency']);
        $c = (new Model($db, 'currency'))->addFields(['currency','name']);

        $u->hasMany('cur', [$c, 'our_field'=>'currency', 'their_field'=>'currency']);

        $cc = $u->load(1)->ref('cur');
        $cc->tryLoadAny(); $this->assertEquals('Euro', $cc['name']);

        $cc = $u->load(2)->ref('cur');
        $cc->tryLoadAny(); $this->assertEquals('Pound', $cc['name']);
    }

    public function testLink2()
    {
        $db = new Persistence_SQL($this->db->connection);
        $u = (new Model($db, 'user'))->addFields(['name','currency_code']);
        $c = (new Model($db, 'currency'))->addFields(['code','name']);

        $u->hasMany('cur', [$c, 'our_field'=>'currency_code', 'their_field'=>'code']);

        $this->assertEquals(
            'select `id`,`code`,`name` from `currency` where `code` = `user`.`currency_code`',
            $u->refLink('cur')->action('select')->render()
        );
    }

    public function testBasicOne()
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
        $o = (new Model($db, 'order'))->addFields(['amount']);

        $o->hasOne('user_id', $u);


        $this->assertEquals('John', $o->load(1)->ref('user_id')['name']); 
        $this->assertEquals('Peter', $o->load(2)->ref('user_id')['name']); 
        $this->assertEquals('John', $o->load(3)->ref('user_id')['name']); 
        $this->assertEquals('Joe', $o->load(5)->ref('user_id')['name']); 

        $o->unload();
        $o->addCondition('amount', '>', 6);
        $o->addCondition('amount', '<', 9);

        $this->assertEquals(
            'select `id`,`name` from `user` where `id` in (select `user_id` from `order` where `amount` > :a and `amount` < :b)',
            $o->ref('user_id')->action('select')->render()
        );
    }

    public function testRelatedExpression()
    {
        $vat = 0.23;
        $a = [
            'invoice'=>[
                1=>['id'=>1, 'ref_no'=>'INV203'],
                2=>['id'=>2, 'ref_no'=>'INV204'],
                3=>['id'=>3, 'ref_no'=>'INV205'],
            ], 'invoice_line'=>[
                ['total_net'=>($n=10), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>1],
                ['total_net'=>($n=30), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>1],
                ['total_net'=>($n=100), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>2],
                ['total_net'=>($n=25), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>3],
                ['total_net'=>($n=25), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>3],
            ]];

        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i=(new Model($db, 'invoice'))->addFields(['ref_no']);
        $l=(new Model($db, 'invoice_line'))->addFields(['invoice_id','total_net','total_vat','total_gross']);
        $i->hasMany('line', $l);

        $i->addExpression('total_net', $i->refLink('line')->action('fx', ['sum', 'total_net']));

        $this->assertEquals(
            'select `invoice`.`id`,`invoice`.`ref_no`,(select sum(`total_net`) from `invoice_line` where `invoice_id` = `invoice`.`id`) `total_net` from `invoice`',
            $i->action('select')->render()
        );
    }

    public function testAggregateHasMany()
    {
        $vat = 0.23;
        $a = [
            'invoice'=>[
                1=>['id'=>1, 'ref_no'=>'INV203'],
                2=>['id'=>2, 'ref_no'=>'INV204'],
                3=>['id'=>3, 'ref_no'=>'INV205'],
            ], 'invoice_line'=>[
                ['total_net'=>($n=10), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>1],
                ['total_net'=>($n=30), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>1],
                ['total_net'=>($n=100), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>2],
                ['total_net'=>($n=25), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>3],
                ['total_net'=>($n=25), 'total_vat'=>($n*$vat), 'total_gross'=>($n*($vat+1)), 'invoice_id'=>3],
            ]];

        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i=(new Model($db, 'invoice'))->addFields(['ref_no']);
        $l=(new Model($db, 'invoice_line'))->addFields(['invoice_id','total_net','total_vat','total_gross']);
        $i->hasMany('line', $l)
            ->addFields([
                ['total_vat', 'aggregate'=>'sum'],
                ['total_net', 'aggregate'=>'sum'],
                ['total_gross', 'aggregate'=>'sum'],
        ]);
    }
}
