<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Exception as CoreException;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Reference;
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
        $order->addField('user_id', ['type' => 'bigint']);

        $r1 = $user->getModel()->hasMany('Orders', ['model' => $order, 'caption' => 'My Orders']);
        $o = $user->ref('Orders')->createEntity();

        self::assertSame(20, $o->get('amount'));
        self::assertSame(1, $o->get('user_id'));

        $r2 = $user->getModel()->hasMany('BigOrders', ['model' => static function () {
            $m = new Model(null, ['table' => 'big_order']);
            $m->addField('amount', ['default' => 100]);
            $m->addField('user_id', ['type' => 'bigint']);

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
        $order->addField('user_id', ['type' => 'bigint']);

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
        $order->addField('user_id', ['type' => 'bigint']);

        $user->hasMany('Orders', ['model' => $order]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Reference with such name already exists');
        $user->hasMany('Orders', ['model' => $order]);
    }

    public function testHasOneDuplicateNameException(): void
    {
        $order = new Model(null, ['table' => 'order']);
        $user = new Model($this->db, ['table' => 'user']);

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
        $order->addField('user_id', ['type' => 'bigint']);

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

    public function testHasOneInferOurFieldType(): void
    {
        $userModel = new class($this->db) extends Model {
            public $table = 'user';

            #[\Override]
            protected function init(): void
            {
                parent::init();

                $this->getIdField()->type = 'date';
            }
        };

        $order = new Model($this->db, ['table' => 'order']);
        $order->hasOne('user_id', ['model' => [get_class($userModel)]]);
        $order->hasOne('a', ['model' => $userModel, 'theirField' => 'id']);
        $order->hasOne('b', ['model' => static function () use ($userModel) {
            $m = clone $userModel;
            $m->addField('x', ['type' => 'float']);

            return $m;
        }, 'theirField' => 'x']);

        self::assertSame(['id' => 'date'], array_map(static fn (Field $f) => $f->type, $userModel->getFields()));
        self::assertSame(['id' => 'date'], array_map(static fn (Field $f) => $f->type, $order->ref('a')->getFields()));
        self::assertSame(['id' => 'date', 'x' => 'float'], array_map(static fn (Field $f) => $f->type, $order->ref('b')->getFields()));
        self::assertSame(['id' => 'bigint', 'user_id' => 'date', 'a' => 'date', 'b' => 'float'], array_map(static fn (Field $f) => $f->type, $order->getFields()));
    }

    public function testRefTypeMismatchWithDisabledCheck(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $order = new Model($this->db, ['table' => 'order']);
        $order->addField('placed_by_user_id');

        $order->hasOne('placed_by', ['model' => $user, 'ourField' => 'placed_by_user_id', 'checkTheirType' => false]);

        self::assertSame('user', $order->ref('placed_by')->table);
        self::assertSame('string', $order->getField('placed_by_user_id')->type);
        self::assertSame('bigint', $order->ref('placed_by')->getIdField()->type);
    }

    public function testCreateTheirModelMissingModelSeedException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Seed must be an array or an object');
        $m->hasOne('foo', []);
    }

    public function testCreateTheirModelInvalidModelSeedException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Seed must be an array or an object');
        $m->hasOne('foo', ['model' => Model::class]);
    }

    public function testCreateAnalysingTheirModelRecursiveInit(): void
    {
        $userModelClass = get_class(new class extends Model {
            /** @var list<string> */
            public static array $logs = [];

            public static int $c;

            /** @var class-string<Model> */
            public static string $orderModelClass;

            public $table = 'user';

            /** @var array<string, Field> */
            public array $analysingOrderModelFields;

            #[\Override]
            protected function init(): void
            {
                parent::init();

                $i = ++self::$c;
                self::$logs[] = '> ' . $this->table . ' ' . $i;

                $this->addField('name');

                $this->hasMany('orders', ['model' => [self::$orderModelClass], 'theirField' => 'user_id'])
                    ->addField('orders_count', ['type' => 'bigint', 'aggregate' => 'count']);

                $this->analysingOrderModelFields = $this->getReference('orders')->createAnalysingTheirModel()->getFields();

                self::$logs[] = '< ' . $this->table . ' ' . $i;
            }
        });
        $userModelClass::$c = -1;

        $orderModelClass = get_class(new class extends Model {
            public static int $c;

            /** @var class-string<Model> */
            public static string $userModelClass;

            public $table = 'order';

            /** @var array<string, Field> */
            public array $analysingUserModelFields;

            #[\Override]
            protected function init(): void
            {
                parent::init();

                $i = ++self::$c;
                self::$userModelClass::$logs[] = '> ' . $this->table . ' ' . $i;

                $this->addField('name');

                $this->hasOne('user', ['model' => [self::$userModelClass], 'ourField' => 'user_id'])
                    ->addTitle();

                $this->analysingUserModelFields = $this->getReference('user')->createAnalysingTheirModel()->getFields();

                self::$userModelClass::$logs[] = '< ' . $this->table . ' ' . $i;
            }
        });
        $orderModelClass::$c = -1;

        $userModelClass::$orderModelClass = $orderModelClass;
        $orderModelClass::$userModelClass = $userModelClass;

        self::assertSame([], $userModelClass::$logs);
        $userModel = new $userModelClass($this->db);
        $expectedLogs = ['> user 0', '> order 0', '> user 1', '< user 1', '< order 0', '< user 0'];
        self::assertSame($expectedLogs, $userModelClass::$logs);
        $orderModel = new $orderModelClass($this->db);
        $expectedLogs = array_merge($expectedLogs, ['> order 1', '< order 1']);
        self::assertSame($expectedLogs, $userModelClass::$logs);

        self::assertSame(['id', 'name', 'user_id', 'user'], array_keys($userModel->analysingOrderModelFields));
        self::assertSame(['id', 'name', 'orders_count'], array_keys($orderModel->analysingUserModelFields));
        self::assertSame($expectedLogs, $userModelClass::$logs);

        new $userModelClass($this->db);
        $expectedLogs = array_merge($expectedLogs, ['> user 2', '< user 2']);
        self::assertSame($expectedLogs, $userModelClass::$logs);

        $userModelClass::$logs = [];
    }

    public function testCreateAnalysingTheirModelKeepReferencedByPersistenceIfSeedIsClassNameOnly(): void
    {
        $theirModelClass = get_class(new class extends Model {
            public $table = 'foo';

            /** @var list<string> */
            public static array $logs = [];

            #[\Override]
            protected function init(): void
            {
                parent::init();

                self::$logs[] = $this->table;
            }
        });

        $refASeed = [$theirModelClass];
        $refBSeed = [$theirModelClass, 'table' => 'bar'];

        $m = new Model($this->db, ['table' => 'user']);
        $refA = $m->hasOne('a', ['model' => $refASeed]);
        $refB = $m->hasOne('b', ['model' => $refBSeed]);
        $m->hasOne('a2', ['model' => $refASeed]);
        $m->hasOne('b2', ['model' => $refBSeed]);
        self::assertSame(['foo', 'bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refA->createAnalysingTheirModel()->table);
        self::assertSame('bar', $refB->createAnalysingTheirModel()->table);
        self::assertSame(['foo', 'bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refA->createTheirModel()->table);
        self::assertSame('bar', $refB->createTheirModel()->table);
        self::assertSame(['foo', 'bar', 'foo', 'bar'], $theirModelClass::$logs);

        $theirModelClass::$logs = [];

        $weakM = \WeakReference::create($m);
        $m = new Model($this->db, ['table' => 'user']);
        unset($refA);
        unset($refB);
        gc_collect_cycles();
        self::assertNull($weakM->get());
        if (\PHP_MAJOR_VERSION === 7) { // force WeakMap polyfill housekeeping
            gc_collect_cycles();
        }
        $refA = $m->hasOne('a', ['model' => $refASeed]);
        $refB = $m->hasOne('b', ['model' => $refBSeed]);
        self::assertSame(['bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refA->createAnalysingTheirModel()->table);
        self::assertSame('bar', $refB->createAnalysingTheirModel()->table);
        self::assertSame(['bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refA->createTheirModel()->table);
        self::assertSame('bar', $refB->createTheirModel()->table);
        self::assertSame(['bar', 'foo', 'bar'], $theirModelClass::$logs);

        $refC = $m->hasMany('c', ['model' => $refASeed, 'theirField' => 'id']);
        $refD = $m->hasMany('d', ['model' => $refBSeed, 'theirField' => 'id']);
        self::assertSame(['bar', 'foo', 'bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refC->createAnalysingTheirModel()->table);
        self::assertSame('bar', $refD->createAnalysingTheirModel()->table);
        self::assertSame(['bar', 'foo', 'bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refC->createTheirModel()->table);
        self::assertSame('bar', $refD->createTheirModel()->table);
        self::assertSame(['bar', 'foo', 'bar', 'foo', 'bar'], $theirModelClass::$logs);

        $theirModelClass::$logs = [];

        $m = new Model(clone $this->db, ['table' => 'user']);
        $refA = $m->hasOne('a', ['model' => $refASeed]);
        $refB = $m->hasOne('b', ['model' => $refBSeed]);
        $m->hasOne('a2', ['model' => $refASeed]);
        $m->hasOne('b2', ['model' => $refBSeed]);
        self::assertSame(['foo', 'bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refA->createAnalysingTheirModel()->table);
        self::assertSame('bar', $refB->createAnalysingTheirModel()->table);
        self::assertSame(['foo', 'bar'], $theirModelClass::$logs);

        self::assertSame('foo', $refA->createTheirModel()->table);
        self::assertSame('bar', $refB->createTheirModel()->table);
        self::assertSame(['foo', 'bar', 'foo', 'bar'], $theirModelClass::$logs);

        $theirModelClass::$logs = [];
    }

    public function testCreateAnalysingTheirModelKeepReferencedByPersistenceIfSeedIsUnboundClosure(): void
    {
        $modelClass = get_class(new class extends Model {
            public $table = 'main';

            /** @var list<string> */
            public static array $logs = [];

            #[\Override]
            protected function init(): void
            {
                parent::init();

                self::$logs[] = $this->table;

                foreach (['_u', '_v'] as $suffix) {
                    $this->hasOne('a' . $suffix, ['model' => static function () {
                        self::$logs[] = 's';

                        return new Model(null, ['table' => 't']);
                    }]);

                    $this->hasOne('b' . $suffix, ['model' => static function () {
                        self::$logs[] = 's_2';

                        return new Model(null, ['table' => 't']);
                    }]);

                    $v = 'use';
                    $this->hasOne('c' . $suffix, ['model' => static function () use ($v) {
                        self::$logs[] = 's_' . $v;

                        return new Model(null, ['table' => 't']);
                    }]);

                    $this->hasOne('d' . $suffix, ['model' => function () {
                        $this->getIdField(); // prevent PHP CS Fixer to make this anonymous function static

                        self::$logs[] = 'bound';

                        return new Model(null, ['table' => 't']);
                    }]);
                }
            }
        });
        self::assertSame([], $modelClass::$logs);

        $m = new $modelClass($this->db);
        self::assertSame(['main', 's', 's_2', 's_use', 'bound'], $modelClass::$logs);
        $modelClass::$logs = [];

        $m2 = new $modelClass(clone $this->db);
        self::assertSame(['main', 's', 's_2', 's_use', 'bound'], $modelClass::$logs);
        $modelClass::$logs = [];

        $m = new $modelClass($this->db);
        self::assertSame(['main', 'bound'], $modelClass::$logs);
        $modelClass::$logs = [];

        if (\PHP_VERSION_ID >= 80300) {
            $weakM = \WeakReference::create($m);
            unset($m);
            gc_collect_cycles();
            self::assertNull($weakM->get());
            $m = new $modelClass($this->db);
            self::assertSame(['main', 's_use', 'bound'], $modelClass::$logs);
            $modelClass::$logs = [];
        }
    }

    public function testCreateAnalysingTheirModelUninitializeIfNotCreated(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $createModelFx = static function () {
            return new class extends Model {
                #[\Override]
                protected function init(): void
                {
                    parent::init();

                    throw new Exception('from init');
                }
            };
        };

        $e = false;
        try {
            $m->hasOne('foo', ['model' => $createModelFx]);
        } catch (Exception $e) {
            $e = $e->getMessage();
        }

        self::assertSame('from init', $e);

        $this->expectException(CoreException::class);
        $this->expectExceptionMessage('Object was not initialized');
        $m->hasOne('foo', ['model' => $createModelFx]);
    }
}
