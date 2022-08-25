<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

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
            ],
            'order' => [
                ['amount' => 20, 'user_id' => 1],
                ['amount' => 15, 'user_id' => 2],
                ['amount' => 5, 'user_id' => 1],
                ['amount' => 3, 'user_id' => 1],
                ['amount' => 8, 'user_id' => 3],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount', ['type' => 'integer']);
        $o->addField('user_id');

        $u->hasMany('Orders', ['model' => $o]);

        $oo = $u->load(1)->ref('Orders');
        $ooo = $oo->load(1);
        static::assertSame(20, $ooo->get('amount'));
        $ooo = $oo->tryLoad(2);
        static::assertNull($ooo);
        $ooo = $oo->load(3);
        static::assertSame(5, $ooo->get('amount'));

        $oo = $u->load(2)->ref('Orders');
        $ooo = $oo->tryLoad(1);
        static::assertNull($ooo);
        $ooo = $oo->load(2);
        static::assertSame(15, $ooo->get('amount'));
        $ooo = $oo->tryLoad(3);
        static::assertNull($ooo);

        $oo = $u->addCondition('id', '>', '1')->ref('Orders');

        $this->assertSameSql(
            'select `id`, `amount`, `user_id` from `order` `_O_7442e29d7d53` where `user_id` in (select `id` from `user` where `id` > :a)',
            $oo->action('select')->render()[0]
        );
    }

    /**
     * Tests to make sure refLink properly generates field links.
     */
    public function testLink(): void
    {
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->addField('user_id');

        $u->hasMany('Orders', ['model' => $o]);

        $this->assertSameSql(
            'select `id`, `amount`, `user_id` from `order` `_O_7442e29d7d53` where `user_id` = `user`.`id`',
            $u->refLink('Orders')->action('select')->render()[0]
        );
    }

    public function testBasic2(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'currency' => 'EUR'],
                ['name' => 'Peter', 'currency' => 'GBP'],
                ['name' => 'Joe', 'currency' => 'EUR'],
            ],
            'currency' => [
                ['currency' => 'EUR', 'name' => 'Euro'],
                ['currency' => 'USD', 'name' => 'Dollar'],
                ['currency' => 'GBP', 'name' => 'Pound'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('currency');

        $c = new Model($this->db, ['table' => 'currency']);
        $c->addField('currency');
        $c->addField('name');

        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $serverVersion = $this->getConnection()->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
            if (preg_match('~^5\.6~', $serverVersion)) {
                static::markTestIncomplete('TODO MySQL: Unique key exceed max key (767 bytes) length');
            }
        }
        $this->markTestIncompleteWhenCreateUniqueIndexIsNotSupportedByPlatform();

        $u->hasOne('cur', ['model' => $c, 'ourField' => 'currency', 'theirField' => 'currency']);
        $this->createMigrator()->createForeignKey($u->getReference('cur'));

        $cc = $u->load(1)->ref('cur');
        static::assertSame('Euro', $cc->get('name'));

        $cc = $u->load(2)->ref('cur');
        static::assertSame('Pound', $cc->get('name'));
    }

    public function testLink2(): void
    {
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('currency_code');

        $c = new Model($this->db, ['table' => 'currency']);
        $c->addField('code');
        $c->addField('name');

        $u->hasMany('cur', ['model' => $c, 'ourField' => 'currency_code', 'theirField' => 'code']);

        $this->assertSameSql(
            'select `id`, `code`, `name` from `currency` `_c_b5fddf1ef601` where `code` = `user`.`currency_code`',
            $u->refLink('cur')->action('select')->render()[0]
        );
    }

    /**
     * Tests that condition defined on the parent model is retained when traversing through hasMany.
     */
    public function testBasicOne(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
            'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');

        $o->hasOne('user_id', ['model' => $u]);

        static::assertSame('John', $o->load(1)->ref('user_id')->get('name'));
        static::assertSame('Peter', $o->load(2)->ref('user_id')->get('name'));
        static::assertSame('John', $o->load(3)->ref('user_id')->get('name'));
        static::assertSame('Joe', $o->load(5)->ref('user_id')->get('name'));

        $o->addCondition('amount', '>', 6);
        $o->addCondition('amount', '<', 9);

        $this->assertSameSql(
            'select `id`, `name` from `user` `_u_e8701ad48ba0` where `id` in (select `user_id` from `order` where (`amount` > :a and `amount` < :b))',
            $o->ref('user_id')->action('select')->render()[0]
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
            ],
            'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('date', ['type' => 'date']);

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u])->addFields([
            'username' => 'name',
            ['date', 'type' => 'date'],
        ]);

        static::assertSame('John', $o->load(1)->get('username'));
        static::assertEquals(new \DateTime('2001-01-02 UTC'), $o->load(1)->get('date'));

        static::assertSame('Peter', $o->load(2)->get('username'));
        static::assertSame('John', $o->load(3)->get('username'));
        static::assertSame('Joe', $o->load(5)->get('username'));

        // few more tests
        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u])->addFields([
            'username' => 'name',
            'thedate' => ['date', 'type' => 'date'],
        ]);
        static::assertSame('John', $o->load(1)->get('username'));
        static::assertEquals(new \DateTime('2001-01-02 UTC'), $o->load(1)->get('thedate'));

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u])->addField('date', null, ['type' => 'date']);
        static::assertEquals(new \DateTime('2001-01-02 UTC'), $o->load(1)->get('date'));
    }

    public function testRelatedExpression(): void
    {
        $vat = 0.23;

        $this->setDb([
            'invoice' => [
                1 => ['id' => 1, 'ref_no' => 'INV203'],
                2 => ['id' => 2, 'ref_no' => 'INV204'],
                3 => ['id' => 3, 'ref_no' => 'INV205'],
            ],
            'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('ref_no');

        $l = new Model($this->db, ['table' => 'invoice_line']);
        $l->addField('invoice_id');
        $l->addField('total_net');
        $l->addField('total_vat');
        $l->addField('total_gross');

        $i->hasMany('line', ['model' => $l]);
        $i->addExpression('total_net', ['expr' => $i->refLink('line')->action('fx', ['sum', 'total_net'])]);

        $this->assertSameSql(
            'select `id`, `ref_no`, (select sum(`total_net`) from `invoice_line` `_l_6438c669e0d0` where `invoice_id` = `invoice`.`id`) `total_net` from `invoice`',
            $i->action('select')->render()[0]
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
            ],
            'invoice_line' => [
                ['total_net' => ($n = 10), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 30), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 1],
                ['total_net' => ($n = 100), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 2],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
                ['total_net' => ($n = 25), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1)), 'invoice_id' => 3],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('ref_no');

        $l = new Model($this->db, ['table' => 'invoice_line']);
        $l->addField('invoice_id');
        $l->addField('total_net', ['type' => 'atk4_money']);
        $l->addField('total_vat', ['type' => 'atk4_money']);
        $l->addField('total_gross', ['type' => 'atk4_money']);

        $i->hasMany('line', ['model' => $l])->addFields([
            'total_net' => ['aggregate' => 'sum'],
            'total_vat' => ['aggregate' => 'sum', 'type' => 'atk4_money'],
            'total_gross' => ['aggregate' => 'sum', 'type' => 'atk4_money'],
        ]);
        $i = $i->load('1');

        // type was set explicitly
        static::assertSame('atk4_money', $i->getField('total_vat')->type);

        // type was not set and is not inherited
        static::assertNull($i->getField('total_net')->type);

        static::assertSame(40.0, (float) $i->get('total_net'));
        static::assertSame(9.2, $i->get('total_vat'));
        static::assertSame(49.2, $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => ($n = 1), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
            ['total_net' => ($n = 2), 'total_vat' => ($n * $vat), 'total_gross' => ($n * ($vat + 1))],
        ]);
        $i->reload();

        static::assertSame($n = 43.0, (float) $i->get('total_net'));
        static::assertSame($n * $vat, $i->get('total_vat'));
        static::assertSame($n * ($vat + 1), $i->get('total_gross'));

        $i->ref('line')->import([
            ['total_net' => null, 'total_vat' => null, 'total_gross' => 1],
        ]);
        $i->reload();

        static::assertSame($n = 43.0, (float) $i->get('total_net'));
        static::assertSame($n * $vat, $i->get('total_vat'));
        static::assertSame($n * ($vat + 1) + 1, $i->get('total_gross'));
    }

    public function testOtherAggregates(): void
    {
        $vat = 0.23;

        $this->setDb([
            'list' => [
                1 => ['id' => 1, 'name' => 'Meat'],
                2 => ['id' => 2, 'name' => 'Veg'],
                3 => ['id' => 3, 'name' => 'Fruit'],
            ],
            'item' => [
                ['name' => 'Apple', 'code' => 'ABC', 'list_id' => 3],
                ['name' => 'Banana', 'code' => 'DEF', 'list_id' => 3],
                ['name' => 'Pork', 'code' => 'GHI', 'list_id' => 1],
                ['name' => 'Chicken', 'code' => null, 'list_id' => 1],
                ['name' => 'Pear', 'code' => null, 'list_id' => 3],
            ],
        ]);

        $buildLengthSqlFx = function (string $v): string {
            return ($this->getDatabasePlatform() instanceof SQLServerPlatform ? 'LEN' : 'LENGTH') . '(' . $v . ')';
        };

        $buildSumWithIntegerCastSqlFx = function (string $v): string {
            if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform
                || $this->getDatabasePlatform() instanceof SQLServerPlatform) {
                $v = 'CAST(' . $v . ' AS INT)';
            }

            return 'SUM(' . $v . ')';
        };

        $l = new Model($this->db, ['table' => 'list']);
        $l->addField('name');

        $i = new Model($this->db, ['table' => 'item']);
        $i->addField('list_id');
        $i->addField('name');
        $i->addField('code');

        $l->hasMany('Items', ['model' => $i])->addFields([
            'items_name' => ['aggregate' => 'count', 'field' => 'name', 'type' => 'integer'],
            'items_code' => ['aggregate' => 'count', 'field' => 'code', 'type' => 'integer'], // counts only not-null values
            'items_star' => ['aggregate' => 'count', 'type' => 'integer'], // no field set, counts all rows with count(*)
            'items_c:' => ['concat' => '::', 'field' => 'name'],
            'items_c-' => ['aggregate' => $i->dsql()->groupConcat($i->expr('[name]'), '-')],
            'len' => ['aggregate' => $i->expr($buildSumWithIntegerCastSqlFx($buildLengthSqlFx('[name]'))), 'type' => 'integer'], // TODO cast should be implicit when using "aggregate", sandpit http://sqlfiddle.com/#!17/0d2c0/3
            'len2' => ['expr' => $buildSumWithIntegerCastSqlFx($buildLengthSqlFx('[name]')), 'type' => 'integer'],
            'chicken5' => ['expr' => $buildSumWithIntegerCastSqlFx('[]'), 'args' => ['5'], 'type' => 'integer'],
        ]);

        $ll = $l->load(1);
        static::assertSame(2, $ll->get('items_name')); // 2 not-null values
        static::assertSame(1, $ll->get('items_code')); // only 1 not-null value
        static::assertSame(2, $ll->get('items_star')); // 2 rows in total
        static::assertSame($ll->get('items_c:') === 'Pork::Chicken' ? 'Pork::Chicken' : 'Chicken::Pork', $ll->get('items_c:'));
        static::assertSame($ll->get('items_c-') === 'Pork-Chicken' ? 'Pork-Chicken' : 'Chicken-Pork', $ll->get('items_c-'));
        static::assertSame(strlen('Chicken') + strlen('Pork'), $ll->get('len'));
        static::assertSame(strlen('Chicken') + strlen('Pork'), $ll->get('len2'));
        static::assertSame(10, $ll->get('chicken5'));

        $ll = $l->load(2);
        static::assertSame(0, $ll->get('items_name'));
        static::assertSame(0, $ll->get('items_code'));
        static::assertSame(0, $ll->get('items_star'));
        static::assertNull($ll->get('items_c:'));
        static::assertNull($ll->get('items_c-'));
        static::assertNull($ll->get('len'));
        static::assertNull($ll->get('len2'));
        static::assertNull($ll->get('chicken5'));
    }

    protected function setupDbForTraversing(): Model
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

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('company_id');

        $company = new Model($this->db, ['table' => 'company']);
        $company->addField('name');

        $user->hasOne('Company', ['model' => $company, 'ourField' => 'company_id', 'theirField' => 'id']);

        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('company_id');
        $order->addField('description');
        $order->addField('amount', ['default' => 20, 'type' => 'float']);

        $company->hasMany('Orders', ['model' => $order]);

        return $user;
    }

    public function testReferenceHasOneTraversing(): void
    {
        $user = $this->setupDbForTraversing();
        $userEntity = $user->load(1);

        static::assertSameExportUnordered([
            ['id' => 1, 'company_id' => '1', 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => 3, 'company_id' => '1', 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $userEntity->ref('Company')->ref('Orders')->export());

        static::assertSameExportUnordered([
            ['id' => 1, 'company_id' => '1', 'description' => 'Vinny Company Order 1', 'amount' => 50.0],
            ['id' => 2, 'company_id' => '2', 'description' => 'Zoe Company Order', 'amount' => 10.0],
            ['id' => 3, 'company_id' => '1', 'description' => 'Vinny Company Order 2', 'amount' => 15.0],
        ], $userEntity->getModel()->ref('Company')->ref('Orders')->export());
    }

    public function testUnloadedEntityTraversingHasOne(): void
    {
        $user = $this->setupDbForTraversing();
        $userEntity = $user->createEntity();

        $companyEntity = $userEntity->ref('Company');
        static::assertFalse($companyEntity->isLoaded());
    }

    public function testUnloadedEntityTraversingHasOneEx(): void
    {
        $user = $this->setupDbForTraversing();
        $user->getReference('Company')->setDefaults(['ourField' => 'id']);
        $userEntity = $user->createEntity();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to traverse on null value');
        $userEntity->ref('Company');
    }

    public function testUnloadedEntityTraversingHasManyEx(): void
    {
        $user = $this->setupDbForTraversing();
        $companyEntity = $user->ref('Company')->createEntity();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to traverse on null value');
        $companyEntity->ref('Orders');
    }

    public function testReferenceHook(): void
    {
        $this->setDb([
            'user' => [
                ['name' => 'John', 'contact_id' => 2],
                ['name' => 'Peter', 'contact_id' => null],
                ['name' => 'Joe', 'contact_id' => 3],
            ],
            'contact' => [
                ['address' => 'Sue contact'],
                ['address' => 'John contact'],
                ['address' => 'Joe contact'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $c = new Model($this->db, ['table' => 'contact']);
        $c->addField('address');

        $u->hasOne('contact_id', ['model' => $c])
            ->addField('address');

        $uu = $u->load(1);
        static::assertSame('John contact', $uu->get('address'));
        static::assertSame('John contact', $uu->ref('contact_id')->get('address'));

        $uu = $u->load(2);
        static::assertNull($uu->get('address'));
        static::assertNull($uu->get('contact_id'));
        static::assertNull($uu->ref('contact_id')->get('address'));

        $uu = $u->load(3);
        static::assertSame('Joe contact', $uu->get('address'));
        static::assertSame('Joe contact', $uu->ref('contact_id')->get('address'));

        $uu = $u->load(2);
        $uu->ref('contact_id')->save(['address' => 'Peters new contact']);

        static::assertNotNull($uu->get('contact_id'));
        static::assertSame('Peters new contact', $uu->ref('contact_id')->get('address'));

        $uu->save()->reload();
        static::assertSame('Peters new contact', $uu->ref('contact_id')->get('address'));
        static::assertSame('Peters new contact', $uu->get('address'));
    }

    public function testHasOneIdFieldAsOurField(): void
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

        $s = (new Model($this->db, ['table' => 'stadium']));
        $s->addField('name');
        $s->addField('player_id', ['type' => 'integer']);

        $this->markTestIncompleteWhenCreateUniqueIndexIsNotSupportedByPlatform();

        $p = new Model($this->db, ['table' => 'player']);
        $p->addField('name');
        $p->delete(2);
        $p->hasOne('Stadium', ['model' => $s, 'ourField' => 'id', 'theirField' => 'player_id']);
        $this->createMigrator()->createForeignKey($p->getReference('Stadium'));

        $s->createEntity()->save(['name' => 'Nou camp nou', 'player_id' => 4]);
        $pEntity = $p->createEntity()->save(['name' => 'Ivan']);

        static::assertSame('Nou camp nou', $pEntity->ref('Stadium')->get('name'));
        static::assertSame(4, $pEntity->ref('Stadium')->get('player_id'));
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user->hasMany('Orders', ['model' => [Model::class, 'table' => 'order'], 'theirField' => 'id']);
        $o = $user->ref('Orders');
        static::assertSame('order', $o->table);
    }

    public function testAddTitle(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
            'order' => [
                ['amount' => '20', 'user_id' => 1],
                ['amount' => '15', 'user_id' => 2],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');

        // by default not set
        $o->hasOne('user_id', ['model' => $u]);
        static::assertSame($o->getField('user_id')->isVisible(), true);

        $o->getReference('user_id')->addTitle();
        static::assertTrue($o->hasField('user'));
        static::assertSame($o->getField('user')->isVisible(), true);
        static::assertSame($o->getField('user_id')->isVisible(), false);

        // if it is set manually then it will not be changed
        $o = new Model($this->db, ['table' => 'order']);
        $o->addField('amount');
        $o->hasOne('user_id', ['model' => $u]);
        $o->getField('user_id')->ui['visible'] = true;
        $o->getReference('user_id')->addTitle();

        static::assertSame($o->getField('user_id')->isVisible(), true);
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
            ],
            'order' => [
                1 => ['id' => 1, 'user_id' => 1],
                2 => ['id' => 2, 'user_id' => 2],
                3 => ['id' => 3, 'user_id' => 1],
            ],
        ];

        $this->setDb($dbData);

        // with default titleField='name'
        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user_id', ['model' => $u])->addTitle();

        // change order user by changing titleField value
        $o = $o->load(1);
        static::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('user', 'Peter');
        static::assertNull($o->get('user_id'));
        $o->save();
        static::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // with custom titleField='last_name'
        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('user_id', ['model' => $u])->addTitle();

        // change order user by changing titleField value
        $o = $o->load(1);
        static::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('user', 'Foo');
        static::assertNull($o->get('user_id'));
        $o->save();
        static::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // with custom titleField='last_name' and custom link name
        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['model' => $u, 'ourField' => 'user_id'])->addTitle();

        // change order user by changing reference field value
        $o = $o->load(1);
        static::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('my_user', 'Foo');
        static::assertNull($o->get('user_id'));
        $o->save();
        static::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // with custom titleField='last_name' and custom link name
        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        $o = (new Model($this->db, ['table' => 'order']));
        $o->hasOne('my_user', ['model' => $u, 'ourField' => 'user_id'])->addTitle();

        // change order user by changing ref field and titleField value - same
        $o = $o->load(1);
        static::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('my_user', 'Foo'); // user_id = 2
        $o->set('user_id', 2);
        static::assertSame(2, $o->get('user_id'));
        $o->save();
        static::assertSame(2, $o->get('user_id'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        // change order user by changing ref field and titleField value - mismatched
        $o = $o->getModel()->load(1);
        static::assertSame(1, $o->get('user_id'));
        $o->set('user_id', null);
        $o->save();
        $o->set('my_user', 'Foo'); // user_id = 2
        $o->set('user_id', 3);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Imported field was changed to an unexpected value');
        $o->save();
    }

    /**
     * Tests that if we change hasOne->addTitle() field value then it will also update
     * link field value when saved.
     */
    public function testHasOneReferenceCaption(): void
    {
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

        $u = new Model($this->db, ['table' => 'user', 'titleField' => 'last_name']);
        $u->addField('name');
        $u->addField('last_name');

        // Test : Now the caption is null and is generated from field name
        static::assertSame('Last Name', $u->getField('last_name')->getCaption());

        $u->getField('last_name')->caption = 'Surname';

        // Test : Now the caption is not null and the value is returned
        static::assertSame('Surname', $u->getField('last_name')->getCaption());

        $o = (new Model($this->db, ['table' => 'order']));
        $orderUserRef = $o->hasOne('my_user', ['model' => $u, 'ourField' => 'user_id']);
        $orderUserRef->addField('user_last_name', 'last_name');

        $referencedCaption = $o->getField('user_last_name')->getCaption();

        // Test : $field->caption for the field 'last_name' is defined in referenced model (User)
        // When Order add field from Referenced model User
        // caption will be passed to Order field user_last_name
        static::assertSame('Surname', $referencedCaption);
    }

    /**
     * Test if field type is taken from referenced Model if not set in HasOne::addField().
     */
    public function testHasOneReferenceType(): void
    {
        $this->setDb([
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
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('last_name');
        $user->addField('some_number');
        $user->addField('some_other_number');
        $user->getField('some_number')->type = 'integer';
        $user->getField('some_other_number')->type = 'integer';
        $order = (new Model($this->db, ['table' => 'order']));
        $orderUserRef = $order->hasOne('my_user', ['model' => $user, 'ourField' => 'user_id']);

        // no type set in defaults, should pull type integer from user model
        $orderUserRef->addField('some_number');
        static::assertSame('integer', $order->getField('some_number')->type);

        // set type in defaults, this should have higher priority than type set in Model
        $orderUserRef->addField('some_other_number', null, ['type' => 'string']);
        static::assertSame('string', $order->getField('some_other_number')->type);
    }
}
