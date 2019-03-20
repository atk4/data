<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * Tests that condition is applied when traversing hasMany
 * also that the original model can be re-loaded with a different
 * value without making any condition stick.
 */
class ReferenceSQLTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasic()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ], ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name']);
        $o = (new Model($this->db, 'order'))->addFields(['amount', 'user_id']);

        $u->hasMany('Orders', $o);

        $oo = $u->load(1)->ref('Orders');
        $oo->tryLoad(1);
        $this->assertEquals(20, $oo['amount']);
        $oo->tryLoad(2);
        $this->assertEquals(null, $oo['amount']);
        $oo->tryLoad(3);
        $this->assertEquals(5, $oo['amount']);

        $oo = $u->load(2)->ref('Orders');
        $oo->tryLoad(1);
        $this->assertEquals(null, $oo['amount']);
        $oo->tryLoad(2);
        $this->assertEquals(15, $oo['amount']);
        $oo->tryLoad(3);
        $this->assertEquals(null, $oo['amount']);

        $oo = $u->unload()->addCondition('id', '>', '1')->ref('Orders');
        if ($this->driver == 'sqlite') {
            $this->assertEquals(
                'select "id","amount","user_id" from "order" where "user_id" in (select "id" from "user" where "id" > :a)',
                $oo->action('select')->render()
            );
        }
    }

    /**
     * Tests to make sure refLink properly generates field links.
     */
    public function testLink()
    {
        $u = (new Model($this->db, 'user'))->addFields(['name']);
        $o = (new Model($this->db, 'order'))->addFields(['amount', 'user_id']);

        $u->hasMany('Orders', $o);

        if ($this->driver == 'sqlite') {
            $this->assertEquals(
                'select "id","amount","user_id" from "order" where "user_id" = "user"."id"',
                $u->refLink('Orders')->action('select')->render()
            );
        }
    }

    public function testBasic2()
    {
        $a = [
            'user' => [
                ['name' => 'John', 'currency' => 'EUR'],
                ['name' => 'Peter', 'currency' => 'GBP'],
                ['name' => 'Joe', 'currency' => 'EUR'],
            ], 'currency' => [
                ['currency' => 'EUR', 'name' => 'Euro'],
                ['currency' => 'USD', 'name' => 'Dollar'],
                ['currency' => 'GBP', 'name' => 'Pound'],
            ], ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name', 'currency']);
        $c = (new Model($this->db, 'currency'))->addFields(['currency', 'name']);

        $u->hasMany('cur', [$c, 'our_field' => 'currency', 'their_field' => 'currency']);

        $cc = $u->load(1)->ref('cur');
        $cc->tryLoadAny();
        $this->assertEquals('Euro', $cc['name']);

        $cc = $u->load(2)->ref('cur');
        $cc->tryLoadAny();
        $this->assertEquals('Pound', $cc['name']);
    }

    public function testLink2()
    {
        $u = (new Model($this->db, 'user'))->addFields(['name', 'currency_code']);
        $c = (new Model($this->db, 'currency'))->addFields(['code', 'name']);

        $u->hasMany('cur', [$c, 'our_field' => 'currency_code', 'their_field' => 'code']);

        if ($this->driver == 'sqlite') {
            $this->assertEquals(
                'select "id","code","name" from "currency" where "code" = "user"."currency_code"',
                $u->refLink('cur')->action('select')->render()
            );
        }
    }

    /**
     * Tests that condition defined on the parent model is retained when traversing
     * through hasMany.
     */
    public function testBasicOne()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ], ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name']);
        $o = (new Model($this->db, 'order'))->addFields(['amount']);

        $o->hasOne('user_id', $u);

        $this->assertEquals('John', $o->load(1)->ref('user_id')['name']);
        $this->assertEquals('Peter', $o->load(2)->ref('user_id')['name']);
        $this->assertEquals('John', $o->load(3)->ref('user_id')['name']);
        $this->assertEquals('Joe', $o->load(5)->ref('user_id')['name']);

        $o->unload();
        $o->addCondition('amount', '>', 6);
        $o->addCondition('amount', '<', 9);

        if ($this->driver == 'sqlite') {
            $this->assertEquals(
                'select "id","name" from "user" where "id" in (select "user_id" from "order" where "amount" > :a and "amount" < :b)',
                $o->ref('user_id')->action('select')->render()
            );
        }
    }

    /**
     * Tests Join::addField's ability to create expressions from foreign fields.
     */
    public function testAddOneField()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '2001-01-02'],
                2 => ['id' => 2, 'name' => 'Peter', 'date' => '2004-08-20'],
                3 => ['id' => 3, 'name' => 'Joe', 'date' => '2005-08-20'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ], ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name', ['date', 'type' => 'date']]);

        $o = (new Model($this->db, 'order'))->addFields(['amount']);
        $o->hasOne('user_id', $u)->addFields(['username' => 'name', ['date', 'type' => 'date']]);

        $this->assertEquals('John', $o->load(1)['username']);
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)['date']);

        $this->assertEquals('Peter', $o->load(2)['username']);
        $this->assertEquals('John', $o->load(3)['username']);
        $this->assertEquals('Joe', $o->load(5)['username']);

        // few more tests
        $o = (new Model($this->db, 'order'))->addFields(['amount']);
        $o->hasOne('user_id', $u)->addFields(['username' => 'name', 'thedate' => ['date', 'type' => 'date']]);
        $this->assertEquals('John', $o->load(1)['username']);
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)['thedate']);

        $o = (new Model($this->db, 'order'))->addFields(['amount']);
        $o->hasOne('user_id', $u)->addFields(['date'], ['type' => 'date']);
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)['date']);
    }

    public function testRelatedExpression()
    {
        $vat = 0.23;
        $a = [
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                2 => ['id' => 2, 'ref_no' => 'INV204'],
                3 => ['id' => 3, 'ref_no' => 'INV205'],
            ], 'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ], ];

        $this->setDB($a);

        $i = (new Model($this->db, 'invoice'))->addFields(['ref_no']);
        $l = (new Model($this->db, 'invoice_line'))->addFields(['invoice_id', 'total_net', 'total_vat', 'total_gross']);
        $i->hasMany('line', $l);

        $i->addExpression('total_net', $i->refLink('line')->action('fx', ['sum', 'total_net']));

        if ($this->driver == 'sqlite') {
            $this->assertEquals(
                'select "invoice"."id","invoice"."ref_no",(select sum("total_net") from "invoice_line" where "invoice_id" = "invoice"."id") "total_net" from "invoice"',
                $i->action('select')->render()
            );
        }
    }

    public function testAggregateHasMany()
    {
        $vat = 0.23;
        $a = [
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                2 => ['id' => 2, 'ref_no' => 'INV204'],
                3 => ['id' => 3, 'ref_no' => 'INV205'],
            ], 'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ], ];

        $this->setDB($a);

        $i = (new Model($this->db, 'invoice'))->addFields(['ref_no']);
        $l = (new Model($this->db, 'invoice_line'))->addFields([
            'invoice_id',
            ['total_net', 'type'=>'money'],
            ['total_vat', 'type'=>'money'],
            ['total_gross', 'type'=>'money'],
        ]);
        $i->hasMany('line', $l)
            ->addFields([
                ['total_vat', 'aggregate' => 'sum', 'type'=>'money'],
                ['total_net', 'aggregate' => 'sum'],
                ['total_gross', 'aggregate' => 'sum'],
        ]);
        $i->load('1');

        // type was set explicitly
        $this->assertEquals('money', $i->getElement('total_vat')->type);

        // type was not set and is not inherited
        $this->assertEquals(null, $i->getElement('total_net')->type);

        $this->assertEquals(40, $i['total_net']);
        $this->assertEquals(9.2, $i['total_vat']);
        $this->assertEquals(49.2, $i['total_gross']);

        $i->ref('line')->import([
                ['total_net' => ($n = 1), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
                ['total_net' => ($n = 2), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
            ]);
        $i->reload();

        $this->assertEquals($n = 43, $i['total_net']);
        $this->assertEquals($n * $vat, $i['total_vat']);
        $this->assertEquals($n * ($vat + 1), $i['total_gross']);

        $i->ref('line')->import([
                ['total_net' => null, 'total_vat' => null, 'total_gross' => 1],
            ]);
        $i->reload();

        $this->assertEquals($n = 43, $i['total_net']);
        $this->assertEquals($n * $vat, $i['total_vat']);
        $this->assertEquals($n * ($vat + 1) + 1, $i['total_gross']);
    }

    public function testOtherAggregates()
    {
        $vat = 0.23;
        $a = [
            'list' => [
                1 => ['id' => 1, 'name' => 'Meat'],
                2 => ['id' => 2, 'name' => 'Veg'],
                3 => ['id' => 3, 'name' => 'Fruit'],
            ], 'item' => [
                ['name' => 'Apple',  'code' => 'ABC', 'list_id'=>3],
                ['name' => 'Banana', 'code' => 'DEF', 'list_id'=>3],
                ['name' => 'Pork',   'code' => 'GHI', 'list_id'=>1],
                ['name' => 'Chicken', 'code'=> null,  'list_id'=>1],
                ['name' => 'Pear',   'code' => null,  'list_id'=>3],
            ], ];

        $this->setDB($a);

        $l = (new Model($this->db, 'list'))->addFields(['name']);
        $i = (new Model($this->db, 'item'))->addFields(['list_id', 'name', 'code']);
        $l->hasMany('Items', $i)
            ->addFields([
                ['items_name', 'aggregate' => 'count', 'field' => 'name'],
                ['items_code', 'aggregate' => 'count', 'field' => 'code'], // counts only not-null values
                ['items_star', 'aggregate' => 'count'], // no field set, counts all rows with count(*)
                ['items_c:',  'concat' => '::', 'field'=>'name'],
                ['items_c-',  'aggregate' => $i->dsql()->groupConcat($i->expr('[name]'), '-')],
                ['len',       'aggregate' => $i->expr('sum(length([name]))')],
                ['len2',      'expr' => 'sum(length([name]))'],
                ['chicken5',  'expr' => 'sum([])', 'args'=>['5']],
        ]);
        $l->load(1);

        $this->assertEquals(2, $l['items_name']); // 2 not-null values
        $this->assertEquals(1, $l['items_code']); // only 1 not-null value
        $this->assertEquals(2, $l['items_star']); // 2 rows in total
        $this->assertEquals('Pork::Chicken', $l['items_c:']);
        $this->assertEquals('Pork-Chicken', $l['items_c-']);
        $this->assertEquals(strlen('Chicken') + strlen('Pork'), $l['len']);
        $this->assertEquals(strlen('Chicken') + strlen('Pork'), $l['len2']);
        $this->assertEquals(10, $l['chicken5']);

        $l->load(2);
        $this->assertEquals(0, $l['items_name']);
        $this->assertEquals(0, $l['items_code']);
        $this->assertEquals(0, $l['items_star']);
        $this->assertEquals('', $l['items_c:']);
        $this->assertEquals('', $l['items_c-']);
        $this->assertEquals(null, $l['len']);
        $this->assertEquals(null, $l['len2']);
        $this->assertEquals(null, $l['chicken5']);
    }

    public function testReferenceHook()
    {
        $a = [
            'user' => [
                ['name' => 'John', 'contact_id' => 2],
                ['name' => 'Peter', 'contact_id' => null],
                ['name' => 'Joe', 'contact_id' => 3],
            ], 'contact' => [
                ['address' => 'Sue contact'],
                ['address' => 'John contact'],
                ['address' => 'Joe contact'],
            ], ];

        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name']);
        $c = (new Model($this->db, 'contact'))->addFields(['address']);

        $u->hasOne('contact_id', $c)
            ->addField('address');

        $u->load(1);
        $this->assertEquals('John contact', $u['address']);
        $this->assertEquals('John contact', $u->ref('contact_id')['address']);

        $u->load(2);
        $this->assertEquals(null, $u['address']);
        $this->assertEquals(null, $u['contact_id']);
        $this->assertEquals(null, $u->ref('contact_id')['address']);

        $u->load(3);
        $this->assertEquals('Joe contact', $u['address']);
        $this->assertEquals('Joe contact', $u->ref('contact_id')['address']);

        $u->load(2);
        $u->ref('contact_id')->save(['address' => 'Peters new contact']);

        $this->assertNotEquals(null, $u['contact_id']);
        $this->assertEquals('Peters new contact', $u->ref('contact_id')['address']);

        $u->save()->reload();
        $this->assertEquals('Peters new contact', $u->ref('contact_id')['address']);
        $this->assertEquals('Peters new contact', $u['address']);
    }

    public function testModelProperty()
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user->hasMany('Orders', ['model' => ['atk4/data/Model', 'table' => 'order'], 'their_field' => 'id']);
        $o = $user->ref('Orders');
        $this->assertEquals('order', $o->table);
    }

    /**
     * Few tests to test Reference_SQL_One addTitle() method.
     */
    public function testAddTitle()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
            ], ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name']);
        $o = (new Model($this->db, 'order'))->addFields(['amount']);

        // by default not set
        $o->hasOne('user_id', $u);
        $this->assertEquals($o->getElement('user_id')->isVisible(), true);

        $o->getRef('user_id')->addTitle();
        $this->assertEquals((bool) $o->hasElement('user'), true);
        $this->assertEquals($o->getElement('user')->isVisible(), true);
        $this->assertEquals($o->getElement('user_id')->isVisible(), false);

        // if it is set manually then it will not be changed
        $o = (new Model($this->db, 'order'))->addFields(['amount']);
        $o->hasOne('user_id', $u);
        $o->getElement('user_id')->ui['visible'] = true;
        $o->getRef('user_id')->addTitle();

        $this->assertEquals($o->getElement('user_id')->isVisible(), true);
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneTitleSet()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                2 => ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                3 => ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ], 'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                2 => ['id' => 2, 'user_id' => 2],
                3 => ['id' => 3, 'user_id' => 1],
            ], ];

        // restore DB
        $this->setDB($a);

        // with default title_field='name'
        $u = (new Model($this->db, 'user'))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, 'order'));
        $o->hasOne('user_id', $u)->addTitle();

        // change order user by changing title_field value
        $o->load(1);
        $o->set('user', 'Peter');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDB($a);

        // with custom title_field='last_name'
        $u = (new Model($this->db, ['user', 'title_field'=>'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, 'order'));
        $o->hasOne('user_id', $u)->addTitle();

        // change order user by changing title_field value
        $o->load(1);
        $o->set('user', 'Foo');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDB($a);

        // with custom title_field='last_name' and custom link name
        $u = (new Model($this->db, ['user', 'title_field'=>'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, 'order'));
        $o->hasOne('my_user', [$u, 'our_field'=>'user_id'])->addTitle();

        // change order user by changing ref field value
        $o->load(1);
        $o->set('my_user', 'Foo');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDB($a);

        // with custom title_field='last_name' and custom link name
        $u = (new Model($this->db, ['user', 'title_field'=>'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, 'order'));
        $o->hasOne('my_user', [$u, 'our_field'=>'user_id'])->addTitle();

        // change order user by changing ref field value
        $o->load(1);
        $o->set('my_user', 'Foo'); // user_id=2
        $o->set('user_id', 3);     // user_id=3 (this will take precedence)
        $this->assertEquals(3, $o->get('user_id'));
        $o->save();
        $this->assertEquals(3, $o->get('user_id')); // user_id changed to Goofy ID
        $o->reload();
        $this->assertEquals(3, $o->get('user_id')); // and it's really saved like that
    }
}
