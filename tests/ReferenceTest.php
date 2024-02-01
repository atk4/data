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
        $order->addField('id', ['type' => 'integer']);
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id', ['type' => 'integer']);

        $r1 = $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders')->createEntity();

        self::assertSame(20, $o->get('amount'));
        self::assertSame(1, $o->get('user_id'));

        $r2 = $user->getModel()->hasMany('BigOrders', ['model' => static function () {
            $m = new Model();
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id', ['type' => 'integer']);

            return $m;
        }]);

        self::assertSame(100, $user->ref('BigOrders')->createEntity()->get('amount'));

        self::assertSame([
            'Orders' => $r1,
            'BigOrders' => $r2,
        ], $user->getModel()->getReferences());
        self::assertSame($r1, $user->getModel()->getReference('Orders'));
        self::assertSame($r2, $user->getModel()->getReference('BigOrders'));
        self::assertTrue($user->getModel()->hasReference('BigOrders'));
        self::assertFalse($user->getModel()->hasReference('SmallOrders'));
    }

    public function testModelCaption(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $user->addField('id', ['type' => 'integer']);
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1);

        $order = new Model();
        $order->addField('id', ['type' => 'integer']);
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id', ['type' => 'integer']);

        $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        self::assertSame('My Orders', $user->refModel('Orders')->getModelCaption());
        self::assertSame('My Orders', $user->ref('Orders')->getModelCaption());
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user = $user->createEntity();
        $user->setId(1);
        $user->getModel()->hasOne('order_id', ['model' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order_id');
        self::assertSame('order', $o->table);
    }

    public function testRefName1(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $order = new Model();
        $order->addField('user_id', ['type' => 'integer']);

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

    public function testCustomReference(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $m->addReference('archive', ['model' => static function (Model $m) {
            return new $m(null, ['table' => $m->table . '_archive']);
        }]);

        self::assertSame('user_archive', $m->ref('archive')->table);
    }

    public function testTheirFieldNameGuessTableWithSchema(): void
    {
        $user = new Model($this->db, ['table' => 'db1.user']);
        $order = new Model($this->db, ['table' => 'db2.orders']);
        $order->addField('user_id', ['type' => 'integer']);

        $user->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        self::assertSame($user->getReference('Orders')->getTheirFieldName(), 'user_id');
    }

    public function testRefTypeMismatchOneException(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('placed_by_user_id');

        $order->hasOne('placed_by', ['model' => $user, 'ourField' => 'placed_by_user_id']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reference type mismatch');
        $order->ref('placed_by');
    }

    public function testRefTypeMismatchManyException(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('placed_by_user_id');

        $user->hasMany('orders', ['model' => $order, 'theirField' => 'placed_by_user_id']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reference type mismatch');
        $user->ref('orders');
    }

    public function testRefTypeMismatchWithDisabledCheck(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('placed_by_user_id');

        $order->hasOne('placed_by', ['model' => $user, 'ourField' => 'placed_by_user_id', 'checkTheirType' => false]);

        self::assertSame('user', $order->ref('placed_by')->table);
    }
}
