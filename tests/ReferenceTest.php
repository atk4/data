<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Exception as CoreException;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class ReferenceTest extends TestCase
{
    public function testBasicReferences(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1);

        $order = new Model(null, ['table' => 'order']);
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id', ['type' => 'integer']);

        $r1 = $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders')->createEntity();

        self::assertSame(20, $o->get('amount'));
        self::assertSame(1, $o->get('user_id'));

        $r2 = $user->getModel()->hasMany('BigOrders', ['model' => static function () {
            $m = new Model(null, ['table' => 'big_order']);
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
        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user = $user->createEntity();
        $user->setId(1);

        $order = new Model(null, ['table' => 'order']);
        $order->addField('amount', ['default' => 20]);
        $order->addField('user_id', ['type' => 'integer']);

        $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);

        // test caption of containsOne reference
        self::assertSame('My Orders', $user->getModel()->getReference('Orders')->createTheirModel()->getModelCaption());
        self::assertSame('My Orders', $user->ref('Orders')->getModelCaption());
    }

    public function testModelProperty(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user = $user->createEntity();
        $user->setId(1);
        $user->getModel()->hasOne('order_id', ['model' => [Model::class, 'table' => 'order']]);
        $o = $user->ref('order_id');
        self::assertSame('order', $o->getModel()->table);
    }

    public function testRefLinkEntityException(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $user = $user->createEntity();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected model, but instance is an entity');
        $user->refLink('order');
    }

    public function testHasManyDuplicateNameException(): void
    {
        $user = new Model(null, ['table' => 'user']);
        $order = new Model();
        $order->addField('user_id', ['type' => 'integer']);

        $user->hasMany('Orders', ['model' => $order]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reference with such name already exists');
        $user->hasMany('Orders', ['model' => $order]);
    }

    public function testHasOneDuplicateNameException(): void
    {
        $order = new Model(null, ['table' => 'order']);
        $user = new Model(null, ['table' => 'user']);

        $user->hasOne('user_id', ['model' => $user]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reference with such name already exists');
        $user->hasOne('user_id', ['model' => $user]);
    }

    public function testCustomReference(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $m->addReference('archive', ['model' => static function () use ($m) {
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
        self::assertSame('user_id', $user->getReference('Orders')->getTheirFieldName());
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
        self::assertSame('string', $order->getField('placed_by_user_id')->type);
        self::assertSame('integer', $order->ref('placed_by')->getIdField()->type);
    }

    public function testCreateTheirModelMissingModelSeedException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Seed must be an array or an object');
        $m->hasOne('foo', [])
            ->createTheirModel();
    }

    public function testCreateTheirModelInvalidModelSeedException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Seed must be an array or an object');
        $m->hasOne('foo', ['model' => Model::class])
            ->createTheirModel();
    }
}
