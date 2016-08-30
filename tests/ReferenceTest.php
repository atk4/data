<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ReferenceTest extends TestCase
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
}
