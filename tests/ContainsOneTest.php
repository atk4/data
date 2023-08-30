<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
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
                $m->fieldName()->addr => null,
            ],
            [
                $m->fieldName()->id => 2,
                $m->fieldName()->ref_no => 'A2',
                $m->fieldName()->addr => null,
            ],
        ]);
    }

    public function testModelCaption(): void
    {
        $i = new Invoice($this->db);
        $a = $i->addr;

        // test caption of containsOne reference
        self::assertSame('Secret Code', $a->getField($a->fieldName()->door_code)->getCaption());
        self::assertSame('Secret Code', $a->refModel($a->fieldName()->door_code)->getModelCaption());
        self::assertSame('Secret Code', $a->door_code->getModelCaption());
    }

    public function testContainsOne(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        self::assertSame(Address::class, get_class($i->getModel()->addr));

        // check do we have address set
        self::assertNull($i->addr); // @phpstan-ignore-line
        $a = $i->getModel()->addr->createEntity();
        $a->containedInEntity = $i;

        // now store some address
        $a->setMulti($row = [
            $a->fieldName()->id => 1,
            $a->fieldName()->country_id => 1,
            $a->fieldName()->address => 'foo',
            $a->fieldName()->built_date => new \DateTime('2019-01-01'),
            $a->fieldName()->tags => ['foo', 'bar'],
            $a->fieldName()->door_code => null,
        ]);
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
        $c->containedInEntity = $iEntity;
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
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore-line

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
        self::assertNull($i->addr->door_code); // @phpstan-ignore-line

        // and now delete address
        $i->addr->delete();
        self::assertNull($i->get($i->fieldName()->addr));
        self::assertNull($i->addr); // @phpstan-ignore-line
    }

    /**
     * How containsOne performs when not all values are stored or there are more values in DB than fields in model.
     */
    public function testContainsOneWhenChangeModelFields(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        // with address
        self::assertNull($i->addr); // @phpstan-ignore-line
        $a = $i->getModel()->addr->createEntity();
        $a->containedInEntity = $i;
        $a->setMulti($row = [
            $a->fieldName()->id => 1,
            $a->fieldName()->country_id => 1,
            $a->fieldName()->address => 'foo',
            $a->fieldName()->built_date => new \DateTime('2019-01-01'),
            $a->fieldName()->tags => [],
            $a->fieldName()->door_code => null,
        ]);
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

    public function testUnmanagedDataModificationException(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ContainsXxx does not support unmanaged data modification');
        $i->set($i->fieldName()->addr, [0]);
    }
}
