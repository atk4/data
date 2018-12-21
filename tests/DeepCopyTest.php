<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Util\DeepCopy;

class DCClient extends Model
{
    public $table = 'client';

    public function init()
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Invoices', new DCInvoice());
        $this->hasMany('Quotes', new DCQuote());
        $this->hasMany('Payments', new DCPayment());
    }
}

class DCInvoice extends Model
{
    public $table = 'invoice';

    public function init()
    {
        parent::init();

        $this->hasOne('client_id', new DCClient());

        $this->hasMany('Lines', [new DCInvoiceLine(), 'their_field'=>'parent_id'])
            ->addField('total', ['aggregate'=>'sum', 'field'=>'total']);

        $this->hasMany('Payments', new DCPayment())
            ->addField('paid', ['aggregate'=>'sum', 'field'=>'amount']);

        $this->addExpression('due', '[total]-[paid]');

        $this->addField('ref');

        $this->addField('is_paid', ['type'=>'boolean', 'default'=>false]);

        $this->addHook('afterCopy', function ($m, $s) {
            if (get_class($s) == get_class($this)) {
                $m['ref'] = $m['ref'].'_copy';
            }
        });
    }
}

class DCQuote extends Model
{
    public $table = 'quote';

    public function init()
    {
        parent::init();
        $this->hasOne('client_id', new DCClient());

        $this->hasMany('Lines', [new DCQuoteLine(), 'their_field'=>'parent_id'])
            ->addField('total', ['aggregate'=>'sum', 'field'=>'total']);

        $this->addField('ref');

        $this->addField('is_converted', ['type'=>'boolean', 'default'=>false]);
    }
}

class DCInvoiceLine extends Model
{
    public $table = 'line';

    public function init()
    {
        parent::init();
        $this->hasOne('parent_id', new DCInvoice());

        $this->addField('name');

        $this->addField('type', ['enum'=>['invoice', 'quote']]);
        $this->addCondition('type', '=', 'invoice');

        $this->addField('qty', ['type'=>'integer', 'mandatory'=>true]);
        $this->addField('price', ['type'=>'money']);
        $this->addField('vat', ['type'=>'numeric', 'default'=>0.21]);

        // total is calculated with VAT
        $this->addExpression('total', '[qty]*[price]*(1+vat)');
    }
}

class DCQuoteLine extends Model
{
    public $table = 'line';

    public function init()
    {
        parent::init();

        $this->hasOne('parent_id', new DCQuote());

        $this->addField('name');

        $this->addField('type', ['enum'=>['invoice', 'quote']]);
        $this->addCondition('type', '=', 'quote');

        $this->addField('qty', ['type'=>'integer']);
        $this->addField('price', ['type'=>'money']);

        // total is calculated WITHOUT VAT
        $this->addExpression('total', '[qty]*[price]');
    }
}

class DCPayment extends Model
{
    public $table = 'payment';

    public function init()
    {
        parent::init();
        $this->hasOne('client_id', new DCClient());

        $this->hasOne('invoice_id', new DCInvoice());

        $this->addField('amount', ['type'=>'money']);
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class DeepCopyTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function setUp()
    {
        parent::setUp();

        // populate database for our three models
        $this->getMigration(new DCClient($this->db))->drop()->create();
        $this->getMigration(new DCInvoice($this->db))->drop()->create();
        $this->getMigration(new DCQuote($this->db))->drop()->create();
        $this->getMigration(new DCInvoiceLine($this->db))->drop()->create();
        $this->getMigration(new DCPayment($this->db))->drop()->create();
    }

    public function testBasic()
    {
        $client = new DCClient($this->db);
        $client_id = $client->insert('John');

        $quote = new DCQuote($this->db);

        $quote->insert(['ref'=> 'q1', 'client_id'=>$client_id, 'Lines'=> [
            ['tools', 'qty'=>5, 'price'=>10],
            ['work', 'qty'=>1, 'price'=>40],
        ]]);
        $quote->loadAny();

        // total price should match
        $this->assertEquals(90.00, $quote['total']);

        $dc = new DeepCopy();
        $invoice = $dc
            ->from($quote)
            ->to(new DCInvoice())
            ->with(['Lines'])
            ->copy();

        // price now will be with VAT
        $this->assertEquals('q1', $invoice['ref']);
        $this->assertEquals(108.90, $invoice['total']);
        $this->assertEquals(1, $invoice->id);

        // Note that we did not specify that 'client_id' should be copied, so same value here
        $this->assertEquals($quote['client_id'], $invoice['client_id']);
        $this->assertEquals('John', $invoice->ref('client_id')['name']);

        // now to add payment for the invoice. Payment originates from the same client as noted on the invoice
        $invoice->ref('Payments')->insert(['amount'=>$invoice['total'] - 5, 'client_id'=>$invoice['client_id']]);

        $invoice->reload();

        // now that invoice is mostly paid, due amount will reflect that
        $this->assertEquals(5, $invoice['due']);

        // Next we copy invocie into simply a new record. Duplicate. However this time we will also duplicate payments,
        // and client. Because Payment references client too, we need to duplicate that one also, this way new record
        // structure will not be related to any existing records.
        $dc = new DeepCopy();
        $invoice_copy = $dc
            ->from($invoice)
            ->to(new DCInvoice())
            ->with(['Lines', 'client_id', 'Payments'=>['client_id']])
            ->copy();

        // Invoice copy receives a new ID
        $this->assertNotEquals($invoice->id, $invoice_copy->id);
        $this->assertEquals('q1_copy', $invoice_copy['ref']);

        // ..however the due amount is the same - 5
        $this->assertEquals(5, $invoice_copy['due']);

        // ..client record was created in the process
        $this->assertNotEquals($invoice_copy['client_id'], $invoice['client_id']);

        // ..but he is still called John
        $this->assertEquals('John', $invoice_copy->ref('client_id')['name']);

        // finally, the client_id used for newly created payment and new invoice correspond
        $this->assertEquals($invoice_copy['client_id'], $invoice_copy->ref('Payments')->loadAny()['client_id']);

        // the final test is to copy client entirely!

        $dc = new DeepCopy();
        $client3 = $dc
            ->from((new DCClient($this->db))->load(1))
            ->to(new DCClient())
            ->with([

                // Invoices are copied, but unless we also copy lines, totals won't be there!
                'Invoices'=> [
                    'Lines',
                ],
                'Quotes'=> [
                    'Lines',
                ],
                'Payments'=> [

                    // this is important to have here, because we want copied payments to be linked with NEW invoices!
                    'invoice_id',
                ],
            ])
            ->copy();

        // New client receives new ID, but also will have all the relevant records copied
        $this->assertEquals(3, $client3->id);

        // We should have one of each records for this new client
        $this->assertEquals(1, $client3->ref('Invoices')->action('count')->getOne());
        $this->assertEquals(1, $client3->ref('Quotes')->action('count')->getOne());
        $this->assertEquals(1, $client3->ref('Payments')->action('count')->getOne());

        // We created invoice for 90 for client1, so after copying it should still be 90
        $this->assertEquals(90, $client3->ref('Quotes')->action('fx', ['sum', 'total'])->getOne());

        // The total of the invoice we copied, should remain, it's calculated based on lines
        $this->assertEquals(108.9, $client3->ref('Invoices')->action('fx', ['sum', 'total'])->getOne());

        // Payments by this clients should also be copied correctly
        $this->assertEquals(103.9, $client3->ref('Payments')->action('fx', ['sum', 'amount'])->getOne());

        // If copied payments are properly allocated against copied invoices, then due amount will be 5
        $this->assertEquals(5, $client3->ref('Invoices')->action('fx', ['sum', 'due'])->getOne());
    }

    /**
     * @expectedException \atk4\data\Util\DeepCopyException
     */
    public function testError()
    {
        $client = new DCClient($this->db);
        $client_id = $client->insert('John');

        $quote = new DCQuote($this->db);
        $quote->hasMany('Lines2', [new DCQuoteLine(), 'their_field'=>'parent_id']);

        $quote->insert(['ref'=> 'q1', 'client_id'=>$client_id, 'Lines'=> [
            ['tools', 'qty'=>5, 'price'=>10],
            ['work', 'qty'=>1, 'price'=>40],
        ]]);
        $quote->loadAny();

        $invoice = new DCInvoice();
        $invoice->addHook('afterCopy', function ($m) {
            if (!$m['ref']) {
                throw new \atk4\core\Exception('no ref');
            }
        });

        // total price should match
        $this->assertEquals(90.00, $quote['total']);

        $dc = new DeepCopy();

        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['ref'])
                ->to($invoice)
                ->with(['Lines', 'Lines2'])
                ->copy();
        } catch (\atk4\data\Util\DeepCopyException $e) {
            $this->assertEquals('no ref', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    /**
     * @expectedException \atk4\data\Util\DeepCopyException
     */
    public function testDeepError()
    {
        $client = new DCClient($this->db);
        $client_id = $client->insert('John');

        $quote = new DCQuote($this->db);

        $quote->insert(['ref'=> 'q1', 'client_id'=>$client_id, 'Lines'=> [
            ['tools', 'qty'=>5, 'price'=>10],
            ['work', 'qty'=>1, 'price'=>40],
        ]]);
        $quote->loadAny();

        $invoice = new DCInvoice();
        $invoice->addHook('afterCopy', function ($m) {
            if (!$m['ref']) {
                throw new \atk4\core\Exception('no ref');
            }
        });

        // total price should match
        $this->assertEquals(90.00, $quote['total']);

        $dc = new DeepCopy();

        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['Lines'=>['qty']])
                ->to($invoice)
                ->with(['Lines'])
                ->copy();
        } catch (\atk4\data\Util\DeepCopyException $e) {
            $this->assertEquals('Mandatory field value cannot be null', $e->getPrevious()->getMessage());

            throw $e;
        }
    }
}
