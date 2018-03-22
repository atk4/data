<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ReferenceTest extends \atk4\core\PHPUnit_AgileTestCase
{
    public function testBasicReferences()
    {
        $user = new Model(['table' => 'user']);
        $user->addField('name');
        $user->id = 1;

        $order = new Model();
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->hasMany('Orders', $order);
        $o = $user->ref('Orders');

        $this->assertEquals(20, $o['amount']);
        $this->assertEquals(1, $o['user_id']);

        $user->hasMany('BigOrders', function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        });

        $this->assertEquals(100, $user->ref('BigOrders')['amount']);
    }

    public function testModelProperty()
    {
        $db = new Persistence();
        $user = new Model($db, ['table' => 'user']);
        $user->id = 1;
        $user->hasOne('order_id', ['model' => ['atk4/data/Model', 'table' => 'order']]);
        $o = $user->ref('order_id');
        $this->assertEquals('order', $o->table);
    }

    /**
     * @expectedException Exception
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
     * @expectedException Exception
     */
    public function testRefName2()
    {
        $order = new Model(['table' => 'order']);
        $user = new Model(['table' => 'user']);

        $user->hasOne('user_id', $user);
        $user->hasOne('user_id', $user);
    }

    /**
     * @expectedException Exception
     */
    public function testRefName3()
    {
        $order = new Model(['table' => 'order']);
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table.'_archive']);
        });
        $order->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table.'_archive']);
        });
    }

    public function testCustomRef()
    {
        $a = [];
        $p = new \atk4\data\Persistence_Array($a);

        $m = new Model($p, ['table' => 'user']);
        $m->addRef('archive', function ($m) {
            return $m->newInstance(null, ['table' => $m->table.'_archive']);
        });

        $this->assertEquals('user_archive', $m->ref('archive')->table);
    }
}
