<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;
use atk4\data\Util\DeepCopy;


class DCInvoice extends Model {
    public $table = 'invoice';
    function init() {
        parent::init();

        $this->hasMany('Lines', [new DCInvoiceLine(), 'their_field'=>'parent_id'])
            ->addField('total', ['aggregate'=>'sum', 'field'=>'total']);

        $this->addField('ref');

        $this->addField('is_paid', ['type'=>'boolean', 'default'=>false]);
    }
}

class DCQuote extends Model {
    public $table = 'quote';
    function init() {
        parent::init();

        $this->hasMany('Lines', [new DCQuoteLine(), 'their_field'=>'parent_id'])
            ->addField('total', ['aggregate'=>'sum', 'field'=>'total']);

        $this->addField('ref');

        $this->addField('is_converted', ['type'=>'boolean', 'default'=>false]);
    }
}

class DCInvoiceLine extends Model {
    public $table = 'line';
    function init() {
        parent::init();
        $this->hasOne('parent_id', new DCInvoice());

        $this->addField('name');

        $this->addField('type', ['enum'=>['invoice','quote']]);
        $this->addCondition('type', '=',  'invoice');

        $this->addField('qty', ['type'=>'integer']);
        $this->addField('price', ['type'=>'money']);
        $this->addField('vat', ['type'=>'numeric', 'default'=>0.21]);

        // total is calculated with VAT
        $this->addExpression('total', '[qty]*[price]*(1+vat)');
    }
}

class DCQuoteLine extends Model {
    public $table = 'line';
    function init() {
        parent::init();

        $this->hasOne('parent_id', new DCQuote());

        $this->addField('name');

        $this->addField('type', ['enum'=>['invoice','quote']]);
        $this->addCondition('type', '=',  'quote');

        $this->addField('qty', ['type'=>'integer']);
        $this->addField('price', ['type'=>'money']);

        // total is calculated WITHOUT VAT
        $this->addExpression('total', '[qty]*[price]');
    }
}





/**
 * Implements various tests for deep copying objects
 */
class DeepCopyTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function setUp()
    {
        parent::setUp();

        // populate database for our three models
        $this->getMigration(new DCInvoice($this->db))->create();
        $this->getMigration(new DCQuote($this->db))->create();
        $this->getMigration(new DCInvoiceLine($this->db))->create();
    }


    public function testBasic()
    {
        $q = new DCQuote($this->db);

        $q->insert(['ref'=>'q1', 'Lines'=> [
            ['tools', 'qty'=>5, 'price'=>10],
            ['work', 'qty'=>1, 'price'=>40],
        ]]);
        $q->loadAny();

        // total price should match
        $this->assertEquals(90, $q['total']);

        $dc = new DeepCopy();
        $invoice = $dc
            ->from($q)
            ->to(new DCInvoice())
            ->with(['Lines'])
            ->copy();

        // price now will be with VAT
        $this->assertEquals(108.9, $invoice['total']);
    }
}
