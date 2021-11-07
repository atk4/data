<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Schema\TestCase;
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

/**
 * ATK Data has support of containsOne / containsMany.
 * Basically data model can contain other data models with one or many records.
 */
class ContainsOneTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our models
        $this->createMigrator(new Country($this->db))->dropIfExists()->create();
        $this->createMigrator(new Invoice($this->db))->dropIfExists()->create();

        // fill in some default values
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

    /**
     * Test caption of referenced model.
     */
    public function testModelCaption(): void
    {
        $a = (new Invoice($this->db))->addr;

        // test caption of containsOne reference
        $this->assertSame('Secret Code', $a->getModel()->getField($a->fieldName()->door_code)->getCaption());
        $this->assertSame('Secret Code', $a->refModel($a->fieldName()->door_code)->getModelCaption());
        $this->assertSame('Secret Code', $a->door_code->getModelCaption());
    }

    /**
     * Test containsOne.
     */
    public function testContainsOne(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        // check do we have address set
        $a = $i->addr;
        $this->assertFalse($a->loaded());

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
        $this->assertEquals($row, $i->addr->get());
        $i->reload();
        $this->assertEquals($row, $i->addr->get());

        // now try to change some field in address
        $i->addr->set($i->addr->fieldName()->address, 'bar')->save();
        $this->assertSame('bar', $i->addr->address);

        // now add nested containsOne - DoorCode
        $c = $i->addr->door_code;
        $c->setMulti($row = [
            $c->fieldName()->id => 1,
            $c->fieldName()->code => 'ABC',
            $c->fieldName()->valid_till => new \DateTime('2019-07-01'),
        ]);
        $c->save();
        $this->assertEquals($row, $i->addr->door_code->get());

        // update DoorCode
        $i->reload();
        $i->addr->door_code->save([$i->addr->door_code->fieldName()->code => 'DEF']);
        $this->assertEquals(array_merge($row, [$i->addr->door_code->fieldName()->code => 'DEF']), $i->addr->door_code->get());

        // try hasOne reference
        $c = $i->addr->country_id;
        $this->assertSame('Latvia', $c->name);
        $i->addr->set($i->addr->fieldName()->country_id, 2)->save();
        $c = $i->addr->country_id;
        $this->assertSame('United Kingdom', $c->name);

        // let's test how it all looks in persistence without typecasting
        $exp_addr = $i->getModel()->setOrder('id')->export(null, null, false)[0][$i->fieldName()->addr];
        $formatDtForCompareFunc = function (\DateTimeInterface $dt): string {
            $dt = (clone $dt)->setTimeZone(new \DateTimeZone('UTC')); // @phpstan-ignore-line

            return $dt->format('Y-m-d H:i:s.u');
        };
        $this->assertJsonStringEqualsJsonString(
            json_encode([
                $i->addr->fieldName()->id => 1,
                $i->addr->fieldName()->country_id => 2,
                $i->addr->fieldName()->address => 'bar',
                $i->addr->fieldName()->built_date => $formatDtForCompareFunc(new \DateTime('2019-01-01')),
                $i->addr->fieldName()->tags => json_encode(['foo', 'bar']),
                $i->addr->fieldName()->door_code => json_encode([
                    $i->addr->door_code->fieldName()->id => 1,
                    $i->addr->door_code->fieldName()->code => 'DEF',
                    $i->addr->door_code->fieldName()->valid_till => $formatDtForCompareFunc(new \DateTime('2019-07-01')),
                ]),
            ]),
            $exp_addr
        );

        // so far so good. now let's try to delete door_code
        $i->addr->door_code->delete();
        $this->assertNull($i->addr->get($i->addr->fieldName()->door_code));
        $this->assertFalse($i->addr->door_code->loaded());

        // and now delete address
        $i->addr->delete();
        $this->assertNull($i->get($i->fieldName()->addr));
        $this->assertFalse($i->addr->loaded());

        //var_dump($i->export(), $i->export(null, null, false));
    }

    /**
     * How containsOne performs when not all values are stored or there are more values in DB than fields in model.
     */
    public function testContainsOneWhenChangeModelFields(): void
    {
        $i = new Invoice($this->db);
        $i = $i->loadBy($i->fieldName()->ref_no, 'A1');

        // with address
        $a = $i->addr;
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

        $this->assertEquals(array_merge($row, ['post_index' => 'LV-1234']), $a->get());

        // now this one is a bit tricky
        // each time you call ref() it returns you new model object so it will not have post_index field
        $this->assertFalse($i->addr->getModel()->hasField('post_index'));

        // now reload invoice just in case
        $i->reload();

        // and it references to same old Address model without post_index field - no errors
        $a = $i->addr;
        $this->assertEquals($row, $a->get());
    }

    /*
     * Model should be loaded before traversing to containsOne relation.
     * Imants: it looks that this is not actually required - disabling.
     */
    /*
    public function testEx1(): void
    {
        $i = new Invoice($this->db);
        $this->expectException(Exception::class);
        $i->addr;
    }
    */
}
