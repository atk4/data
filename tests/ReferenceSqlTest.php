<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;

/**
 * Tests that condition is applied when traversing hasMany
 * also that the original model can be re-loaded with a different
 * value without making any condition stick.
 */
class ReferenceSqlTest extends TestCase
{
    public function testBasic(): void
    {
        $this->setDb([
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
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount', 'user_id']);

        $u->hasMany('Orders', ['model' => $o]);

        $oo = $u->load(1)->ref('Orders');
        $ooo = $oo->tryLoad(1);
        $this->assertEquals(20, $ooo->get('amount'));
        $ooo = $oo->tryLoad(2);
        $this->assertNull($ooo->get('amount'));
        $ooo = $oo->tryLoad(3);
        $this->assertEquals(5, $ooo->get('amount'));

        $oo = $u->load(2)->ref('Orders');
        $ooo = $oo->tryLoad(1);
        $this->assertNull($ooo->get('amount'));
        $ooo = $oo->tryLoad(2);
        $this->assertEquals(15, $ooo->get('amount'));
        $ooo = $oo->tryLoad(3);
        $this->assertNull($ooo->get('amount'));

        $oo = $u->addCondition('id', '>', '1')->ref('Orders');

        $this->assertSameSql(
            'select "id", "amount", "user_id" from "order" "_O_7442e29d7d53" where "user_id" in (select "id" from "user" where "id" > :a)',
            $oo->action('select')->render()
        );
    }

    /**
     * Tests to make sure refLink properly generates field links.
     */
    public function testLink(): void
    {
        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount', 'user_id']);

        $u->hasMany('Orders', ['model' => $o]);

        $this->assertSameSql(
            'select "id", "amount", "user_id" from "order" "_O_7442e29d7d53" where "user_id" = "user"."id"',
            $u->refLink('Orders')->action('select')->render()
        );
    }

    public function testBasic2(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'currency' => 'EUR'],
                ['name' => 'Peter', 'currency' => 'GBP'],
                ['name' => 'Joe', 'currency' => 'EUR'],
            ], 'currency' => [
                ['currency' => 'EUR', 'name' => 'Euro'],
                ['currency' => 'USD', 'name' => 'Dollar'],
                ['currency' => 'GBP', 'name' => 'Pound'],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'currency']);
        $c = (new Model($this->db, ['table' => 'currency']))->addFields(['currency', 'name']);

        $u->hasMany('cur', ['model' => $c, 'our_field' => 'currency', 'their_field' => 'currency']);

        $cc = $u->load(1)->ref('cur');
        $cc = $cc->tryLoadOne();
        $this->assertSame('Euro', $cc->get('name'));

        $cc = $u->load(2)->ref('cur');
        $cc = $cc->tryLoadOne();
        $this->assertSame('Pound', $cc->get('name'));
    }

    public function testLink2(): void
    {
        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'currency_code']);
        $c = (new Model($this->db, ['table' => 'currency']))->addFields(['code', 'name']);

        $u->hasMany('cur', ['model' => $c, 'our_field' => 'currency_code', 'their_field' => 'code']);

        $this->assertSameSql(
            'select "id", "code", "name" from "currency" "_c_b5fddf1ef601" where "code" = "user"."currency_code"',
            $u->refLink('cur')->action('select')->render()
        );
    }

    /**
     * Tests that condition defined on the parent model is retained when traversing
     * through hasMany.
     */
    public function testBasicOne(): void
    {
        $this->setDb([
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
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);

        $o->hasOne('user_id', ['model' => $u]);

        $this->assertSame('John', $o->load(1)->ref('user_id')->get('name'));
        $this->assertSame('Peter', $o->load(2)->ref('user_id')->get('name'));
        $this->assertSame('John', $o->load(3)->ref('user_id')->get('name'));
        $this->assertSame('Joe', $o->load(5)->ref('user_id')->get('name'));

        $o->addCondition('amount', '>', 6);
        $o->addCondition('amount', '<', 9);

        $this->assertSameSql(
            'select "id", "name" from "user" "_u_e8701ad48ba0" where "id" in (select "user_id" from "order" where ("amount" > :a and "amount" < :b))',
            $o->ref('user_id')->action('select')->render()
        );
    }

    /**
     * Tests Join::addField's ability to create expressions from foreign fields.
     */
    public function testAddOneField(): void
    {
        $this->setDb([
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
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'date' => ['type' => 'date']]);

        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user_id', ['model' => $u])->addFields(['username' => 'name', ['date', 'type' => 'date']]);

        $this->assertSame('John', $o->load(1)->get('username'));
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)->get('date'));

        $this->assertSame('Peter', $o->load(2)->get('username'));
        $this->assertSame('John', $o->load(3)->get('username'));
        $this->assertSame('Joe', $o->load(5)->get('username'));

        // few more tests
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user_id', ['model' => $u])->addFields(['username' => 'name', 'thedate' => ['date', 'type' => 'date']]);
        $this->assertSame('John', $o->load(1)->get('username'));
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)->get('thedate'));

        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user_id', ['model' => $u])->addFields(['date'], ['type' => 'date']);
        $this->assertEquals(new \DateTime('2001-01-02'), $o->load(1)->get('date'));
    }

    public function testRelatedExpression(): void
    {
        $vat = 0.23;

        $this->setDb([
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
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['ref_no']);
        $l = (new Model($this->db, ['table' => 'invoice_line']))->addFields(['invoice_id', 'total_net', 'total_vat', 'total_gross']);
        $i->hasMany('line', ['model' => $l]);

        $i->addExpression('total_net', $i->refLink('line')->action('fx', ['sum', 'total_net']));

        $this->assertSameSql(
            'select "invoice"."id", "invoice"."ref_no", (select sum("total_net") from "invoice_line" "_l_6438c669e0d0" where "invoice_id" = "invoice"."id") "total_net" from "invoice"',
            $i->action('select')->render()
        );
    }

    public function testAggregateHasMany(): void
    {
        $vat = 0.23;

        $this->setDb([
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
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['ref_no']);
        $l = (new Model($this->db, ['table' => 'invoice_line']))->addFields([
            'invoice_id',
            'total_net' => ['type' => 'atk4_money'],
            'total_vat' => ['type' => 'atk4_money'],
            'total_gross' => ['type' => 'atk4_money'],
        ]);
        $i->hasMany('line', ['model' => $l])
            ->addFields([
                'total_vat' => ['aggregate' => 'sum', 'type' => 'atk4_money'],
                'total_net' => ['aggregate' => 'sum'],
                'total_gross' => ['aggregate' => 'sum'],
            ]);
        $i = $i->load('1');

        // type was set explicitly
        $this->assertSame('atk4_money', $i->getField('total_vat')->type);

        // type was not set and is not inherited
        $this->assertNull($i->getField('total_net')->type);

        $this->assertEquals(40, $i->get('total_net'));
        $this->assertEquals(9.2, $i->get('total_vat'));
        $this->assertEquals(49.2, $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => ($n = 1), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
            ['total_net' => ($n = 2), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
        ]);
        $i->reload();

        $this->assertEquals($n = 43, $i->get('total_net'));
        $this->assertEquals($n * $vat, $i->get('total_vat'));
        $this->assertEquals($n * ($vat + 1), $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => null, 'total_vat' => null, 'total_gross' => 1],
        ]);
        $i->reload();

        $this->assertEquals($n = 43, $i->get('total_net'));
        $this->assertEquals($n * $vat, $i->get('total_vat'));
        $this->assertEquals($n * ($vat + 1) + 1, $i->get('total_gross'));
    }

    public function testOtherAggregates(): void
    {
        $vat = 0.23;

        $this->setDb([
            'list' => [
                1 => ['id' => 1, 'name' => 'Meat'],
                2 => ['id' => 2, 'name' => 'Veg'],
                3 => ['id' => 3, 'name' => 'Fruit'],
            ], 'item' => [
                ['name' => 'Apple', 'code' => 'ABC', 'list_id' => 3],
                ['name' => 'Banana', 'code' => 'DEF', 'list_id' => 3],
                ['name' => 'Pork', 'code' => 'GHI', 'list_id' => 1],
                ['name' => 'Chicken', 'code' => null, 'list_id' => 1],
                ['name' => 'Pear', 'code' => null, 'list_id' => 3],
            ],
        ]);

        $buildLengthSqlFx = function (string $v): string {
            return ($this->getDatabasePlatform() instanceof SQLServer2012Platform ? 'LEN' : 'LENGTH') . '(' . $v . ')';
        };

        $buildSumWithIntegerCastSqlFx = function (string $v): string {
            if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform
                || $this->getDatabasePlatform() instanceof SQLServer2012Platform) {
                $v = 'CAST(' . $v . ' AS INT)';
            }

            return 'SUM(' . $v . ')';
        };

        $l = (new Model($this->db, ['table' => 'list']))->addFields(['name']);
        $i = (new Model($this->db, ['table' => 'item']))->addFields(['list_id', 'name', 'code']);
        $l->hasMany('Items', ['model' => $i])
            ->addFields([
                'items_name' => ['aggregate' => 'count', 'field' => 'name'],
                'items_code' => ['aggregate' => 'count', 'field' => 'code'], // counts only not-null values
                'items_star' => ['aggregate' => 'count'], // no field set, counts all rows with count(*)
                'items_c:' => ['concat' => '::', 'field' => 'name'],
                'items_c-' => ['aggregate' => $i->dsql()->groupConcat($i->expr('[name]'), '-')],
                'len' => ['aggregate' => $i->expr($buildSumWithIntegerCastSqlFx($buildLengthSqlFx('[name]')))], // TODO cast should be implicit when using "aggregate", sandpit http://sqlfiddle.com/#!17/0d2c0/3
                'len2' => ['expr' => $buildSumWithIntegerCastSqlFx($buildLengthSqlFx('[name]'))],
                'chicken5' => ['expr' => $buildSumWithIntegerCastSqlFx('[]'), 'args' => ['5']],
            ]);

        $ll = $l->load(1);
        $this->assertEquals(2, $ll->get('items_name')); // 2 not-null values
        $this->assertEquals(1, $ll->get('items_code')); // only 1 not-null value
        $this->assertEquals(2, $ll->get('items_star')); // 2 rows in total
        $this->assertSame($ll->get('items_c:') === 'Pork::Chicken' ? 'Pork::Chicken' : 'Chicken::Pork', $ll->get('items_c:'));
        $this->assertSame($ll->get('items_c-') === 'Pork-Chicken' ? 'Pork-Chicken' : 'Chicken-Pork', $ll->get('items_c-'));
        $this->assertEquals(strlen('Chicken') + strlen('Pork'), $ll->get('len'));
        $this->assertEquals(strlen('Chicken') + strlen('Pork'), $ll->get('len2'));
        $this->assertEquals(10, $ll->get('chicken5'));

        $ll = $l->load(2);
        $this->assertEquals(0, $ll->get('items_name'));
        $this->assertEquals(0, $ll->get('items_code'));
        $this->assertEquals(0, $ll->get('items_star'));
        $this->assertEquals('', $ll->get('items_c:'));
        $this->assertEquals('', $ll->get('items_c-'));
        $this->assertNull($ll->get('len'));
        $this->assertNull($ll->get('len2'));
        $this->assertNull($ll->get('chicken5'));
    }

    public function testReferenceHasOneTraversing(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'Vinny', 'company_id' => 1],
                ['name' => 'Zoe', 'company_id' => 2],
            ],
            'company' => [
                ['name' => 'Vinny Company'],
                ['name' => 'Zoe Company'],
            ],
            'order' => [
                ['company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
                ['company_id' => 2, 'description' => 'Zoe Company Order', 'amount' => 10.0],
                ['company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
            ],
        ]);

        $user = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'company_id']);

        $company = (new Model($this->db, ['table' => 'company']))->addFields(['name']);

        $user->hasOne('Company', ['model' => $company, 'our_field' => 'company_id', 'their_field' => 'id']);

        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('company_id');
        $order->addField('description');
        $order->addField('amount', ['default' => 20, 'type' => 'float']);

        $company->hasMany('Orders', ['model' => $order]);

        $user = $user->load(1);

        $firstUserOrders = $user->ref('Company')->ref('Orders');
        $firstUserOrders->setOrder('id');

        $this->assertEquals([
            ['id' => '1', 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => '3', 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $firstUserOrders->export());

        $user->unload();

        $this->assertEquals([
            ['id' => '1', 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => '3', 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $firstUserOrders->export());

        $this->assertEquals([
            ['id' => '1', 'company_id' => 1, 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => '2', 'company_id' => 2, 'description' => 'Zoe Company Order', 'amount' => 10.0],
            ['id' => '3', 'company_id' => 1, 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $user->ref('Company')->ref('Orders')->setOrder('id')->export());
    }

    public function testReferenceHook(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'contact_id' => 2],
                ['name' => 'Peter', 'contact_id' => null],
                ['name' => 'Joe', 'contact_id' => 3],
            ], 'contact' => [
                ['address' => 'Sue contact'],
                ['address' => 'John contact'],
                ['address' => 'Joe contact'],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $c = (new Model($this->db, ['table' => 'contact']))->addFields(['address']);

        $u->hasOne('contact_id', ['model' => $c])
            ->addField('address');

        $uu = $u->load(1);
        $this->assertSame('John contact', $uu->get('address'));
        $this->assertSame('John contact', $uu->ref('contact_id')->get('address'));

        $uu = $u->load(2);
        $this->assertNull($uu->get('address'));
        $this->assertNull($uu->get('contact_id'));
        $this->assertNull($uu->ref('contact_id')->get('address'));

        $uu = $u->load(3);
        $this->assertSame('Joe contact', $uu->get('address'));
        $this->assertSame('Joe contact', $uu->ref('contact_id')->get('address'));

        $uu = $u->load(2);
        $uu->ref('contact_id')->save(['address' => 'Peters new contact']);

        $this->assertNotNull($uu->get('contact_id'));
        $this->assertSame('Peters new contact', $uu->ref('contact_id')->get('address'));

        $uu->save()->reload();
        $this->assertSame('Peters new contact', $uu->ref('contact_id')->get('address'));
        $this->assertSame('Peters new contact', $uu->get('address'));
    }

    /**
     * test case hasOne::our_key == owner::id_field.
     */
    public function testIdFieldReferenceOurFieldCase(): void
    {
        $this->setDb([
            'player' => [
                ['name' => 'John'],
                ['name' => 'Messi'],
                ['name' => 'Ronaldo'],
            ],
            'stadium' => [
                ['name' => 'Sue bernabeu', 'player_id' => 3],
                ['name' => 'John camp', 'player_id' => 1],
            ],
        ]);

        $p = (new Model($this->db, ['table' => 'player']))->addFields(['name']);

        $s = (new Model($this->db, ['table' => 'stadium']));
        $s->addFields(['name']);
        $s->hasOne('player_id', ['model' => $p]);

        $p->hasOne('Stadium', ['model' => $s, 'our_field' => 'id', 'their_field' => 'player_id']);

        $p = $p->load(2);
        $p->ref('Stadium')->import([['name' => 'Nou camp nou']]);
        $this->assertSame('Nou camp nou', $p->ref('Stadium')->get('name'));
        $this->assertSame(2, $p->ref('Stadium')->get('player_id'));
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user->hasMany('Orders', ['model' => [Model::class, 'table' => 'order'], 'their_field' => 'id']);
        $o = $user->ref('Orders');
        $this->assertSame('order', $o->table);
    }

    /**
     * Few tests to test Reference\HasOneSql addTitle() method.
     */
    public function testAddTitle(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);

        // by default not set
        $o->hasOne('user_id', ['model' => $u]);
        $this->assertSame($o->getField('user_id')->isVisible(), true);

        $o->getRef('user_id')->addTitle();
        $this->assertTrue($o->hasField('user'));
        $this->assertSame($o->getField('user')->isVisible(), true);
        $this->assertSame($o->getField('user_id')->isVisible(), false);

        // if it is set manually then it will not be changed
        $o = (new Model($this->db, ['table' => 'order']))->addFields(['amount']);
        $o->hasOne('user_id', ['model' => $u]);
        $o->getField('user_id')->ui['visible'] = true;
        $o->getRef('user_id')->addTitle();

        $this->assertSame($o->getField('user_id')->isVisible(), true);
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneTitleSet(): void
    {
        $dbData = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                2 => ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                3 => ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ], 'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                2 => ['id' => 2, 'user_id' => 2],
                3 => ['id' => 3, 'user_id' => 1],
            ],
        ];

        // restore DB
        $this->setDb($dbData);

        // with default title_field='name'
        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user_id', ['model' => $u])->addTitle();

        // change order user by changing title_field value
        $o = $o->load(1);
        $o->set('user', 'Peter');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDb($dbData);

        // with custom title_field='last_name'
        $u = (new Model($this->db, ['table' => 'user', 'title_field' => 'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user_id', ['model' => $u])->addTitle();

        // change order user by changing title_field value
        $o = $o->load(1);
        $o->set('user', 'Foo');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDb($dbData);

        // with custom title_field='last_name' and custom link name
        $u = (new Model($this->db, ['table' => 'user', 'title_field' => 'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['model' => $u, 'our_field' => 'user_id'])->addTitle();

        // change order user by changing ref field value
        $o = $o->load(1);
        $o->set('my_user', 'Foo');
        $this->assertEquals(1, $o->get('user_id'));
        $o->save();
        $this->assertEquals(2, $o->get('user_id')); // user_id changed to Peters ID
        $o->reload();
        $this->assertEquals(2, $o->get('user_id')); // and it's really saved like that

        // restore DB
        $this->setDb($dbData);

        // with custom title_field='last_name' and custom link name
        $u = (new Model($this->db, ['table' => 'user', 'title_field' => 'last_name']))->addFields(['name', 'last_name']);
        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['model' => $u, 'our_field' => 'user_id'])->addTitle();

        // change order user by changing ref field value
        $o = $o->load(1);
        $o->set('my_user', 'Foo'); // user_id=2
        $o->set('user_id', 3);     // user_id=3 (this will take precedence)
        $this->assertEquals(3, $o->get('user_id'));
        $o->save();
        $this->assertEquals(3, $o->get('user_id')); // user_id changed to Goofy ID
        $o->reload();
        $this->assertEquals(3, $o->get('user_id')); // and it's really saved like that
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneReferenceCaption(): void
    {
        // restore DB
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'last_name' => 'Doe'],
                2 => ['id' => 2, 'name' => 'Peter', 'last_name' => 'Foo'],
                3 => ['id' => 3, 'name' => 'Goofy', 'last_name' => 'Goo'],
            ],
            'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                2 => ['id' => 2, 'user_id' => 2],
                3 => ['id' => 3, 'user_id' => 1],
            ],
        ]);
        $u = (new Model($this->db, ['table' => 'user', 'title_field' => 'last_name']))->addFields(['name', 'last_name']);

        // Test : Now the caption is null and is generated from field name
        $this->assertSame('Last Name', $u->getField('last_name')->getCaption());

        $u->getField('last_name')->caption = 'Surname';

        // Test : Now the caption is not null and the value is returned
        $this->assertSame('Surname', $u->getField('last_name')->getCaption());

        $o = (new Model($this->db, ['table' => 'order']));
        $order_user_ref = $o->hasOne('my_user', ['model' => $u, 'our_field' => 'user_id']);
        $order_user_ref->addField('user_last_name', 'last_name');

        $referenced_caption = $o->getField('user_last_name')->getCaption();

        // Test : $field->caption for the field 'last_name' is defined in referenced model (User)
        // When Order add field from Referenced model User
        // caption will be passed to Order field user_last_name
        $this->assertSame('Surname', $referenced_caption);
    }

    /**
     * HasOne: Test if field type is taken from referenced Model if not set in HasOne::addField().
     * Test if field type is set in HasOne::addField(), it has priority.
     */
    public function testHasOneReferenceType(): void
    {
        // restore DB
        $this->setDb(
            [
                'user' => [
                    1 => [
                        'id' => 1,
                        'name' => 'John',
                        'last_name' => 'Doe',
                        'some_number' => 3,
                        'some_other_number' => 4,
                    ],
                ],
                'order' => [
                    1 => ['id' => 1, 'user_id' => 1],
                ],
            ]
        );
        $user = (new Model($this->db, ['table' => 'user']))->addFields(
            ['name', 'last_name', 'some_number', 'some_other_number']
        );
        $user->getField('some_number')->type = 'integer';
        $user->getField('some_other_number')->type = 'integer';
        $order = (new Model($this->db, ['table' => 'order']));
        $order_UserRef = $order->hasOne('my_user', ['model' => $user, 'our_field' => 'user_id']);

        // no type set in defaults, should pull type integer from user model
        $order_UserRef->addField('some_number');
        $this->assertSame('integer', $order->getField('some_number')->type);

        // set type in defaults, this should have higher priority than type set in Model
        $order_UserRef->addField('some_other_number', null, ['type' => 'string']);
        $this->assertSame('string', $order->getField('some_other_number')->type);
    }
}
