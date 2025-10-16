<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Reference;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Tests\ContainsOne\Address;
use Atk4\Data\Tests\ContainsOne\Country;
use Atk4\Data\Tests\ContainsOne\Invoice;

/**
 * Model structure:.
 *
 * Invoice (SQL)
 *   - containsOne(Address)
 *     - hasOne(Country, SQL)
 *     - containsOne(DoorCode)
 */
class ContainsOneTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMigrator(new Country($this->db))->create();
        $this->createMigrator(new Invoice($this->db))->create();

        $m = new Country($this->db);
        $m->import([
            [
                $m->fieldName()->id => 1,
                $m->fieldName()->name => 'Latvia',
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->name => 'United Kingdom',
            ],
        ]);

        $m = new Invoice($this->db);
        $m->import([
            [
                $m->fieldName()->id => 1,
                $m->fieldName()->ref_no => 'A1',
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->ref_no => 'A2',
            ],
        ]);
    }

    public function testModelCaption(): void
    {
        $i = new Invoice($this->db);
        $a = $i->addr;

        // test caption of containsOne reference
        self::assertSame('Secret Code', $a->getField($a->fieldName()->door_code)->getCaption());
        self::assertSame('Secret Code', $a->getReference($a->fieldName()->door_code)->createTheirModel()->getModelCaption());
        self::assertSame('Secret Code', $a->door_code->getModelCaption());
    }

    public function testContainsOne(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        self::assertSame(Address::class, get_class($i->getModel()->addr));

        // check do we have address set
        self::assertNull($i->addr); // @phpstan-ignore staticMethod.impossibleType
        $a = $i->getModel()->addr->createEntity();
        $a->getModel()->containedInEntity = $i;

        // now store some address
        $a->setMulti($row = [
            $a->fieldName()->id => 1,
            $a->fieldName()->country_id => 1,
            $a->fieldName()->address => 'foo',
            $a->fieldName()->built_date => new \DateTime('2019-01-01'),
            $a->fieldName()->tags => ['foo', 'bar'],
        ]);
        $row[$a->fieldName()->door_code] = null;
        $a->save();

        // now reload invoice and see if it is saved
        self::{'assertEquals'}($row, $i->addr->get());
        $i->reload();
        self::{'assertEquals'}($row, $i->addr->get());
        $i = $i->getModel()->load($i->getId());
        self::{'assertEquals'}($row, $i->addr->get());

        // now try to change some field in address
        $i->addr->set($i->addr->fieldName()->address, 'bar')->save();
        self::assertSame('bar', $i->addr->address);

        // now add nested containsOne - DoorCode
        $iEntity = $i->addr;
        $c = $iEntity->getModel()->door_code->createEntity();
        $c->getModel()->containedInEntity = $iEntity;
        $c->setMulti($row = [
            $c->fieldName()->id => 1,
            $c->fieldName()->code => 'ABC',
            $c->fieldName()->valid_till => new \DateTime('2019-07-01'),
        ]);
        $c->save();
        self::{'assertEquals'}($row, $i->addr->door_code->get());

        // update DoorCode
        $i->reload();
        $i->addr->door_code->save([$i->addr->door_code->fieldName()->code => 'DEF']);
        self::{'assertEquals'}(array_merge($row, [$i->addr->door_code->fieldName()->code => 'DEF']), $i->addr->door_code->get());

        // try hasOne reference
        $c = $i->addr->country_id;
        self::assertSame('Latvia', $c->name);
        $i->addr->set($i->addr->fieldName()->country_id, 2)->save();
        $c = $i->addr->country_id;
        self::assertSame('United Kingdom', $c->name);

        // let's test how it all looks in persistence without typecasting
        $exportAddr = $i->getModel()->setOrder('id')
            ->export(null, null, false)[0][$i->fieldName()->addr];
        $formatDtForCompareFx = static function (\DateTimeInterface $dt): string {
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore method.notFound

            return $dt->format('Y-m-d H:i:s.u');
        };
        self::assertJsonStringEqualsJsonString(
            json_encode([
                $i->addr->fieldName()->id => 1,
                $i->addr->fieldName()->country_id => 2,
                $i->addr->fieldName()->address => 'bar',
                $i->addr->fieldName()->built_date => $formatDtForCompareFx(new \DateTime('2019-01-01')),
                $i->addr->fieldName()->tags => json_encode(['foo', 'bar']),
                $i->addr->fieldName()->door_code => json_encode([
                    $i->addr->door_code->fieldName()->id => 1,
                    $i->addr->door_code->fieldName()->code => 'DEF',
                    $i->addr->door_code->fieldName()->valid_till => $formatDtForCompareFx(new \DateTime('2019-07-01')),
                ]),
            ]),
            $exportAddr
        );

        // so far so good. now let's try to delete door_code
        $i->addr->door_code->delete();
        self::assertNull($i->addr->get($i->addr->fieldName()->door_code));
        self::assertNull($i->addr->door_code); // @phpstan-ignore staticMethod.impossibleType

        // and now delete address
        $i->addr->delete();
        self::assertNull($i->get($i->fieldName()->addr));
        self::assertNull($i->addr); // @phpstan-ignore staticMethod.impossibleType
    }

    /**
     * How containsOne performs when not all values are stored or there are more values in DB than fields in model.
     */
    public function testContainsOneWhenChangeModelFields(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        // with address
        self::assertNull($i->addr); // @phpstan-ignore staticMethod.impossibleType
        $a = $i->getModel()->addr->createEntity();
        $a->getModel()->containedInEntity = $i;
        $a->setMulti($row = [
            $a->fieldName()->id => 1,
            $a->fieldName()->country_id => 1,
            $a->fieldName()->address => 'foo',
            $a->fieldName()->built_date => new \DateTime('2019-01-01'),
            $a->fieldName()->tags => [],
        ]);
        $row[$a->fieldName()->door_code] = null;
        $a->save();

        // now let's add one more field in address model and save
        $a->getModel()->addField('post_index');
        $a->set('post_index', 'LV-1234');
        $a->save();

        self::{'assertEquals'}(array_merge($row, ['post_index' => 'LV-1234']), $a->get());

        // now this one is a bit tricky
        // each time you call ref() it returns you new model object so it will not have post_index field
        self::assertFalse($i->addr->hasField('post_index'));

        // now reload invoice just in case
        $i->reload();

        // and it references to same old Address model without post_index field - no errors
        $a = $i->addr;
        self::{'assertEquals'}($row, $a->get());
    }

    public function testCreateTheirModelContainedInPersistence(): void
    {
        $i = new Invoice($this->db);
        self::assertNotSame($i->getPersistence(), $i->addr->getPersistence());
        self::assertSame($i->getPersistence(), $i->addr->getField($i->addr->fieldName()->country_id)->getReference()->createTheirModel()->getPersistence());
    }

    public function testRefContainedInPersistence(): void
    {
        $i = new Invoice($this->db);
        self::assertNotSame($i->getPersistence(), $i->addr->getPersistence());
        self::assertSame($i->getPersistence(), $i->addr->country_id->getPersistence());
        self::assertSame($i->getPersistence(), $i->addr->door_code->containedInPersistence);
    }

    /**
     * @param mixed $log
     *
     * @param-out list<array{class-string<Model>, Model::HOOK_*}> $log
     */
    private function createInvoiceEntityWithLogger(&$log): Invoice
    {
        $log = [];
        $addLogHooksFx = static function (Model $m) use (&$log) {
            foreach ([Model::HOOK_BEFORE_SAVE, Model::HOOK_AFTER_SAVE, Model::HOOK_BEFORE_DELETE, Model::HOOK_AFTER_DELETE] as $spot) {
                foreach ([\PHP_INT_MIN, \PHP_INT_MAX] as $priority) {
                    $m->onHook($spot, static function (Model $m) use (&$log, $spot, $priority) {
                        $log[] = [get_class($m), $spot, $priority === \PHP_INT_MIN ? '>' : '<'];
                    }, [], $priority);
                }
            }
        };

        $invoice = new Invoice($this->db);
        $addLogHooksFx($invoice);

        $createTheirModelFx = static function () use ($addLogHooksFx) {
            $address = new Address();
            $addLogHooksFx($address);

            return $address;
        };
        \Closure::bind(static fn () => $invoice->getField($invoice->fieldName()->addr)->getReference()->model = $createTheirModelFx, null, Reference::class)();

        $invoiceEntity = $invoice->loadBy($invoice->fieldName()->ref_no, 'A1');

        $invoice->getField($invoice->fieldName()->addr)
            ->getReference()->ref($invoiceEntity)
            ->save(['address' => 'foo']);
        self::assertSame(1, $invoiceEntity->addr->id);
        self::assertSame('foo', $invoiceEntity->addr->address);

        return $invoiceEntity;
    }

    public function testSaveHooks(): void
    {
        $i = $this->createInvoiceEntityWithLogger($log);

        $expectedLog = [
            [Address::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Address::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Address::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Address::class, Model::HOOK_AFTER_SAVE, '<'],
        ];
        self::assertSame($expectedLog, $log);

        $log = [];
        $address = $i->addr;
        $address->address = 'bar';
        self::assertSame([], $log);
        $address->save();

        self::assertSame($expectedLog, $log);

        $log = [];
        $address->save();
        self::assertSame([
            [Address::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Address::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Address::class, Model::HOOK_AFTER_SAVE, '>'],
            [Address::class, Model::HOOK_AFTER_SAVE, '<'],
        ], $log);
    }

    public function testDeleteHooksOnOwnerDelete(): void
    {
        $i = $this->createInvoiceEntityWithLogger($log);

        $log = [];
        $i->delete();

        self::assertSame([
            [Invoice::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Address::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Address::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Address::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Address::class, Model::HOOK_AFTER_DELETE, '<'],
            [Invoice::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Invoice::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_AFTER_DELETE, '<'],
        ], $log);
    }

    public function testDeleteHooksOnContainedDelete(): void
    {
        $i = $this->createInvoiceEntityWithLogger($log);

        $log = [];
        $i->addr->delete();

        self::assertSame([
            [Address::class, Model::HOOK_BEFORE_DELETE, '>'],
            [Address::class, Model::HOOK_BEFORE_DELETE, '<'],
            [Address::class, Model::HOOK_AFTER_DELETE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '>'],
            [Invoice::class, Model::HOOK_BEFORE_SAVE, '<'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '>'],
            [Invoice::class, Model::HOOK_AFTER_SAVE, '<'],
            [Address::class, Model::HOOK_AFTER_DELETE, '<'],
        ], $log);
    }

    public function testDirtyException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $i->getDirtyRef()[$i->fieldName()->addr] = [];

        $theirEntity = $i->getField($i->fieldName()->addr)->getReference()->ref($i);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Field is required to be not dirty');
        $theirEntity->save();
    }

    public function testUnmanagedDataModificationException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $i->getField($i->fieldName()->addr)->normalize([]);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Contained model data cannot be modified directly');
        $i->set($i->fieldName()->addr, [0]);
    }

    public function testUnmanagedDataModificationSetNullException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $i->getField($i->fieldName()->addr)
            ->getReference()->ref($i)
            ->save(['address' => 'foo']);
        self::assertSame('foo', $i->addr->address);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Contained model data cannot be modified directly');
        $i->setNull($i->fieldName()->addr);
    }
}
