<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ReferenceTest extends AtkPhpunit\TestCase
{
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

        $this->assertSame(20, $o['amount']);
        $this->assertSame(1, $o['user_id']);

        $user->hasMany('BigOrders', function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        });

        $this->assertSame(100, $user->ref('BigOrders')['amount']);
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
        $user->hasOne('order_id', ['model' => ['atk4/data/Model', 'table' => 'order']]);
        $o = $user->ref('order_id');
        $this->assertSame('order', $o->table);
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testRefName1()
    {
        $user = new Model(['table' => 'user']);
        $order = new Model();
        $order->addField('user_id');

        $user->hasMany('Orders', $order);
        $user->hasMany('Orders', $order);
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testRefName2()
    {
        $order = new Model(['table' => 'order']);
        $user = new Model(['table' => 'user']);

        $user->hasOne('user_id', $user);
        $user->hasOne('user_id', $user);
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testRefName3()
    {
        $db = new Persistence();
        $order = new Model($db, ['table' => 'order']);
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });
    }

    public function testCustomRef()
    {
        $a = [];
        $p = new Persistence\Array_($a);

        $m = new Model($p, ['table' => 'user']);
        $m->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table . '_archive']);
        });

        $this->assertSame('user_archive', $m->ref('archive')->table);
    }
}
