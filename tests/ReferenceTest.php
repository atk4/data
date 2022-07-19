<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class ReferenceTest extends TestCase
{
    public function testBasicReferences(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id', ['type' => 'integer']);
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1);

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id', ['type' => 'integer']);

        $r1 = $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders')->createEntity();

        $this->assertSame(20, $o->get('amount'));
        $this->assertSame(1, $o->get('user_id'));

        $r2 = $user->getModel()->hasMany('BigOrders', ['model' => function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id');

            return $m;
        }]);

        $this->assertSame(100, $user->ref('BigOrders')->createEntity()->get('amount'));

        $this->assertSame([
            'Orders' => $r1,
            'BigOrders' => $r2,
        ], $user->getModel()->getRefs());
        $this->assertSame($r1, $user->getModel()->getRef('Orders'));
        $this->assertSame($r2, $user->getModel()->getRef('BigOrders'));
        $this->assertTrue($user->getModel()->hasRef('BigOrders'));
        $this->assertFalse($user->getModel()->hasRef('SmallOrders'));
    }

    public function testModelCaption(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id');
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1);

        $order = new Model();
        $order->addField('id');
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id');

        $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        $this->assertSame('My Orders', $user->refModel('Orders')->getModelCaption());
        $this->assertSame('My Orders', $user->ref('Orders')->getModelCaption());
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user = $user->createEntity();
        $user->setId(1);
        $user->getModel()->hasOne('order_id', ['model' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order_id');
        $this->assertSame('order', $o->table);
    }

    public function testRefName1(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $order = new Model();
        $order->addField('user_id');

        $user->hasMany('Orders', ['model' => $order]);

        $this->expectException(Exception::class);
        $user->hasMany('Orders', ['model' => $order]);
    }

    public function testRefName2(): void
    {
        $order = new Model(null, ['table' => 'order']);
        $user = new Model(null, ['table' => 'user']);

        $user->hasOne('user_id', ['model' => $user]);

        $this->expectException(Exception::class);
        $user->hasOne('user_id', ['model' => $user]);
    }

    public function testCustomRef(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $m->addRef('archive', ['model' => function ($m) {
            return new $m(null, ['table' => $m->table . '_archive']);
        }]);

        $this->assertSame('user_archive', $m->ref('archive')->table);
    }

    public function testTheirFieldNameGuessTableWithSchema(): void
    {
        $user = new Model($this->db, ['table' => 'db1.user']);
        $order = new Model($this->db, ['table' => 'db2.orders']);
        $order->addField('user_id');

        $user->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        $this->assertSame($user->getRef('Orders')->getTheirFieldName(), 'user_id');
    }
}
