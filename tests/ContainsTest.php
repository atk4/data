<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * Country model.
 */
class Country extends Model
{
    public $table = 'country';

    public function init()
    {
        parent::init();

        $this->addField('name');
    }
}

/**
 * VAT rate model.
 */
class VatRate extends Model
{
    public $table = 'vat_rate';

    public function init()
    {
        parent::init();

        $this->addField('name');
        $this->addField('rate', ['type' => 'integer']);
    }
}

/**
 * Invoice model.
 */
class Invoice extends Model
{
    public $table = 'invoice';
    public $title_field = 'ref_no';

    public function init()
    {
        parent:: init();

        $this->addField('ref_no', ['required' => true]);
        $this->addField('amount', ['type' => 'money']);

        // will contain one Address
        $this->containsOne('shipping_address', Address::class);

        // will contain many Lines
        $this->containsMany('lines', Line::class);

        // total_gross - calculated by php callback not by SQL expression
        $this->addCalculatedField('total_gross', function ($m) {
            $total = 0;
            foreach ($m->ref('lines') as $line) {
                $total += $line['total_gross'];
            }
            return $total;
        });
    }
}

/**
 * Address model.
 */
class Address extends Model
{
    public function init()
    {
        parent::init();

        $this->hasOne('country_id', Country::class);

        $this->addField('street');
        $this->addField('house');
        $this->addField('built_date', ['type' => 'date']);
    }
}

/**
 * Invoice lines model.
 */
class Line extends Model
{
    public function init()
    {
        parent::init();

        $this->hasOne('vat_rate_id', VatRate::class);

        $this->addField('price', ['type' => 'money', 'required' => true]);
        $this->addField('qty', ['type' => 'float', 'required' => true]);

        $this->addExpression('total_gross', function ($m) {
            return $m['price'] * $m['qty'] * (1 + $m->ref('vat_rate_id')['rate'] / 100);
        });
    }
}

/**
 * @coversDefaultClass Model
 *
 * ATK Data has support of containsOne / containsMany.
 * Basically data model can contain other data models with one or many records.
 */
class ContainsTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function setUp()
    {
        parent::setUp();

        // populate database for our models
        $this->getMigration(new Country($this->db))->drop()->create();
        $this->getMigration(new VatRate($this->db))->drop()->create();
        $this->getMigration(new Invoice($this->db))->drop()->create();

        // fill in some default values
        $m = new Country($this->db);
        $m->import([
            ['id' => 1, 'name' => 'Latvia'],
            ['id' => 2, 'name' => 'United Kingdom'],
        ]);

        $m = new VatRate($this->db);
        $m->import([
            ['id' => 1, 'name' => '21% rate', 'rate' => 21],
            ['id' => 2, 'name' => '15% rate', 'rate' => 15],
        ]);

        $m = new Invoice($this->db);
        $m->import([
            ['id' => 1, 'ref_no' => 'A1', 'amount' => 123],
            ['id' => 2, 'ref_no' => 'A2', 'amount' => 456],
        ]);
    }

    /**
     * Test containsOne.
     */
    public function testContainsOne()
    {
        $i = new Invoice($this->db);
        $i->loadBy('ref_no', 'A1');

        // check do we have shipping address set
        $a = $i->ref('shipping_address');
        $this->assertFalse($a->loaded());

        // now store some address
        $a->set($row = ['country_id'=>1, 'street'=>'Rigas', 'house'=>13, 'built_date'=>new \DateTime('last year')]);
        $a->save();

        // now reload invoice and see if it is saved
        $this->assertEquals($row, $i->ref('shipping_address')->get());
        $i->reload();
        $this->assertEquals($row, $i->ref('shipping_address')->get());

        // now try to change some field in address
        $i->ref('shipping_address')->set('house', 666)->save();
        $this->assertEquals(666, $i->ref('shipping_address')['house']);

        // try hasOne reference
        $c = $i->ref('shipping_address')->ref('country_id');
        $this->assertEquals('Latvia', $c['name']);
        $i->ref('shipping_address')->set('country_id', 2)->save();
        $c = $i->ref('shipping_address')->ref('country_id');
        $this->assertEquals('United Kingdom', $c['name']);

        // so far so good. now let's try to delete that shipping address completely
        $i->ref('shipping_address')->delete();
        $this->assertEquals(null, $i->get('shipping_address'));

        //var_dump($i->export());
    }

    /**
     * How containsOne performs when not all values are stored or there are more values in DB than fields in model.
     */
    public function testContainsOneWhenChangeModelFields()
    {
        $i = new Invoice($this->db);
        $i->loadBy('ref_no', 'A1');

        // with address
        $a = $i->ref('shipping_address');
        $a->set($row = ['country_id'=>1, 'street'=>'Rigas', 'house'=>13, 'built_date'=>new \DateTime('last year')])->save();

        // now let's add one more field in address model
        $a->addField('post_index');
        $a->set('post_index', 'LV-1234');
        $a->save();
        $this->assertEquals(array_merge($row, ['post_index'=>'LV-1234']), $a->get());

        // now reload invoice just in case
        $i->reload();

        // and it references to same old Address model without post_index field - no errors
        $a = $i->ref('shipping_address');
        $this->assertEquals($row, $a->get());
    }

    /**
     * Test containsMany.
     */
    public function testContainsMany()
    {
        $i = new Invoice($this->db);
        $i->loadBy('ref_no', 'A1');

        // now let's add some lines
        $l = $i->ref('lines');
        $rows = [
            1 => ['id' => 1, 'vat_rate_id'=>1, 'price' => 10, 'qty' => 2],
            2 => ['id' => 2, 'vat_rate_id'=>2, 'price' => 15, 'qty' => 5],
            3 => ['id' => 3, 'vat_rate_id'=>1, 'price' => 40, 'qty' => 1],
        ];
        foreach ($rows as $row) {
            $l->insert($row);
        }

        // reload invoice just in case
        $this->assertEquals($rows, $i->ref('lines')->export());
        $i->reload();
        $this->assertEquals($rows, $i->ref('lines')->export());

        // now let's delete line with id=2 and add one more line
        $i->ref('lines')
            ->load(2)->delete()
            ->insert(['vat_rate_id'=>2, 'price' => 50, 'qty' => 3]);
        $rows = [
            1 => ['id' => 1, 'vat_rate_id'=>1, 'price' => 10, 'qty' => 2],
            3 => ['id' => 3, 'vat_rate_id'=>1, 'price' => 40, 'qty' => 1],
            4 => ['id' => 4, 'vat_rate_id'=>2, 'price' => 50, 'qty' => 3],
        ];
        $this->assertEquals($rows, $i->ref('lines')->export());

        // try hasOne reference
        $v = $i->ref('lines')->load(4)->ref('vat_rate_id');
        $this->assertEquals(15, $v['rate']);

        // test expression fields
        $v = $i->ref('lines')->load(4);
        $this->assertEquals(50 * 3 * (1 + 15 / 100), $v['total_gross']);

        // and what about calculated field?
        $i->reload(); // we need to reload invoice for changes in lines to be recalculated
        $this->assertEquals(10*2*(1+21/100) + 40*1*(1+21/100) + 50*3*(1+15/100), $i['total_gross']); // =245.10

        //var_dump($i->export());
    }

    /**
     * Model should be loaded before traversing to containsOne relation.
     *
     * @expectedException Exception
     */
    public function testEx1()
    {
        $i = new Invoice($this->db);
        $i->ref('shipping_address');
    }

    /**
     * Model should be loaded before traversing to containsMany relation.
     *
     * @expectedException Exception
     */
    public function testEx2()
    {
        $i = new Invoice($this->db);
        $i->ref('lines');
    }
}
