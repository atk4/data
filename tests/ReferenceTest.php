<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Exception;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ReferenceTest extends \atk4\schema\PhpunitTestCase
{
    /** @var array */
    private $init_db =
    [
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
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDB($this->init_db);
    }

    public function testBasicReferences()
    {
        $user = new Model(['table' => 'user']);
        $user->addField('name');
        $user->id = 1;

        $order = new Model();
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', [$order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders');

        $this->assertSame(20, $o->get('amount')); // 'amount' default value
        $this->assertSame(1, $o->get('user_id'));

        $user->hasMany('BigOrders', function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        });

        $this->assertSame(100, $user->ref('BigOrders')->get('amount'));
    }

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption()
    {
        $user = new Model(['table' => 'user']);
        $user->addField('name');
        $user->id = 1;

        $order = new Model();
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', [$order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        $this->assertSame('My Orders', $user->refModel('Orders')->getModelCaption());
        $this->assertSame('My Orders', $user->ref('Orders')->getModelCaption());
    }

    public function testModelProperty()
    {
        $db = new Persistence();
        $user = new Model($db, ['table' => 'user']);
        $user->id = 1;
        $user->hasOne('order_id', ['model' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order_id');
        $this->assertSame('order', $o->table);
    }

    public function testRefTraversing()
    {
        $user = new Model($this->db, 'user');
        $user->addField('name');
        $user->addField('company_id');

        $user->id = 1;

        $company = new Model($this->db, 'company');
        $company->addField('name');
        $user->hasOne('Company', [$company, 'our_field' => 'company_id', 'their_field' => 'id']);

        $order = new Model($this->db, 'order');
        $order->addField('company_id');
        $order->addField('amount', ['default' => 20]);

        $company->hasMany('Orders', [$order]);

        $this->assertEquals(20, $user->ref('Company')->ref('Orders')->get('amount')); // 'amount' default value
    }

    public function testRefName1()
    {
        $user = new Model(['table' => 'user']);
        $order = new Model();
        $order->addField('user_id');

        $user->hasMany('Orders', $order);
        $this->expectException(Exception::class);
        $user->hasMany('Orders', $order);
    }

    public function testRefName2()
    {
        $user = new Model(['table' => 'user']);

        $user->hasOne('user_id', $user);
        $this->expectException(Exception::class);
        $user->hasOne('user_id', $user);
    }

    public function testRefName3()
    {
        $db = new Persistence();
        $order = new Model($db, ['table' => 'order']);
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });
        $this->expectException(Exception::class);
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });
    }

    public function testCustomRef()
    {
        $p = new Persistence\Array_();

        $m = new Model($p, ['table' => 'user']);
        $m->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });

        $this->assertSame('user_archive', $m->ref('archive')->table);
    }
}
