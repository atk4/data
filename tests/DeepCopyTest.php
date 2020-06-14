<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Util\DeepCopy;
use atk4\data\Util\DeepCopyException;

class DCClient extends Model
{
    public $table = 'client';

    public function init(): void
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

    public function init(): void
    {
        parent::init();

        $this->hasOne('client_id', new DCClient());

        $this->hasMany('Lines', [new DCInvoiceLine(), 'their_field' => 'parent_id'])
            ->addField('total', ['aggregate' => 'sum', 'field' => 'total']);

        $this->hasMany('Payments', new DCPayment())
            ->addField('paid', ['aggregate' => 'sum', 'field' => 'amount']);

        $this->addExpression('due', '[total]-[paid]');

        $this->addField('ref');

        $this->addField('is_paid', ['type' => 'boolean', 'default' => false]);

        $this->onHook(DeepCopy::HOOK_AFTER_COPY, function ($m, $s) {
            if (get_class($s) === static::class) {
                $m->set('ref', $m->get('ref') . '_copy');
            }
        });
    }
}

class DCQuote extends Model
{
    public $table = 'quote';

    public function init(): void
    {
        parent::init();
        $this->hasOne('client_id', new DCClient());

        $this->hasMany('Lines', [new DCQuoteLine(), 'their_field' => 'parent_id'])
            ->addField('total', ['aggregate' => 'sum', 'field' => 'total']);

        $this->addField('ref');

        $this->addField('is_converted', ['type' => 'boolean', 'default' => false]);
    }
}

class DCInvoiceLine extends Model
{
    public $table = 'line';

    public function init(): void
    {
        parent::init();
        $this->hasOne('parent_id', new DCInvoice());

        $this->addField('name');

        $this->addField('type', ['enum' => ['invoice', 'quote']]);
        $this->addCondition('type', '=', 'invoice');

        $this->addField('qty', ['type' => 'integer', 'mandatory' => true]);
        $this->addField('price', ['type' => 'money']);
        $this->addField('vat', ['type' => 'numeric', 'default' => 0.21]);

        // total is calculated with VAT
        $this->addExpression('total', '[qty]*[price]*(1+[vat])');
    }
}

class DCQuoteLine extends Model
{
    public $table = 'line';

    public function init(): void
    {
        parent::init();

        $this->hasOne('parent_id', new DCQuote());

        $this->addField('name');

        $this->addField('type', ['enum' => ['invoice', 'quote']]);
        $this->addCondition('type', '=', 'quote');

        $this->addField('qty', ['type' => 'integer']);
        $this->addField('price', ['type' => 'money']);

        // total is calculated WITHOUT VAT
        $this->addExpression('total', '[qty]*[price]');
    }
}

class DCPayment extends Model
{
    public $table = 'payment';

    public function init(): void
    {
        parent::init();
        $this->hasOne('client_id', new DCClient());

        $this->hasOne('invoice_id', new DCInvoice());

        $this->addField('amount', ['type' => 'money']);
    }
}

/**
 * Implements various tests for deep copying objects.
 */
class DeepCopyTest extends \atk4\schema\PhpunitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // populate database for our three models
        $this->getMigrator(new DCClient($this->db))->drop()->create();
        $this->getMigrator(new DCInvoice($this->db))->drop()->create();
        $this->getMigrator(new DCQuote($this->db))->drop()->create();
        $this->getMigrator(new DCInvoiceLine($this->db))->drop()->create();
        $this->getMigrator(new DCPayment($this->db))->drop()->create();
    }

    public function testBasic()
    {
        $client = new DCClient($this->db);
        $client_id = $client->insert(['name' => 'John']);

        $quote = new DCQuote($this->db);

        $quote->insert(['ref' => 'q1', 'client_id' => $client_id, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote->loadAny();

        // total price should match
        $this->assertEquals(90.00, $quote->get('total'));

        $dc = new DeepCopy();
        $invoice = $dc
            ->from($quote)
            ->to(new DCInvoice())
            ->with(['Lines'])
            ->copy();

        // price now will be with VAT
        $this->assertSame('q1', $invoice->get('ref'));
        $this->assertEquals(108.90, $invoice->get('total'));
        $this->assertEquals(1, $invoice->id);

        // Note that we did not specify that 'client_id' should be copied, so same value here
        $this->assertSame($quote->get('client_id'), $invoice->get('client_id'));
        $this->assertSame('John', $invoice->ref('client_id')->get('name'));

        // now to add payment for the invoice. Payment originates from the same client as noted on the invoice
        $invoice->ref('Payments')->insert(['amount' => $invoice->get('total') - 5, 'client_id' => $invoice->get('client_id')]);

        $invoice->reload();

        // now that invoice is mostly paid, due amount will reflect that
        $this->assertEquals(5, $invoice->get('due'));

        // Next we copy invocie into simply a new record. Duplicate. However this time we will also duplicate payments,
        // and client. Because Payment references client too, we need to duplicate that one also, this way new record
        // structure will not be related to any existing records.
        $dc = new DeepCopy();
        $invoice_copy = $dc
            ->from($invoice)
            ->to(new DCInvoice())
            ->with(['Lines', 'client_id', 'Payments' => ['client_id']])
            ->copy();

        // Invoice copy receives a new ID
        $this->assertNotSame($invoice->id, $invoice_copy->id);
        $this->assertSame('q1_copy', $invoice_copy->get('ref'));

        // ..however the due amount is the same - 5
        $this->assertEquals(5, $invoice_copy->get('due'));

        // ..client record was created in the process
        $this->assertNotSame($invoice_copy->get('client_id'), $invoice->get('client_id'));

        // ..but he is still called John
        $this->assertSame('John', $invoice_copy->ref('client_id')->get('name'));

        // finally, the client_id used for newly created payment and new invoice correspond
        $this->assertSame($invoice_copy->get('client_id'), $invoice_copy->ref('Payments')->loadAny()->get('client_id'));

        // the final test is to copy client entirely!

        $dc = new DeepCopy();
        $client3 = $dc
            ->from((new DCClient($this->db))->load(1))
            ->to(new DCClient())
            ->with([
                // Invoices are copied, but unless we also copy lines, totals won't be there!
                'Invoices' => [
                    'Lines',
                ],
                'Quotes' => [
                    'Lines',
                ],
                'Payments' => [
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

    public function testError()
    {
        $client = new DCClient($this->db);
        $client_id = $client->insert(['name' => 'John']);

        $quote = new DCQuote($this->db);
        $quote->hasMany('Lines2', [new DCQuoteLine(), 'their_field' => 'parent_id']);

        $quote->insert(['ref' => 'q1', 'client_id' => $client_id, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote->loadAny();

        $invoice = new DCInvoice();
        $invoice->onHook(DeepCopy::HOOK_AFTER_COPY, function ($m) {
            if (!$m->get('ref')) {
                throw new \atk4\core\Exception('no ref');
            }
        });

        // total price should match
        $this->assertEquals(90.00, $quote->get('total'));

        $dc = new DeepCopy();

        $this->expectException(DeepCopyException::class);

        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['ref'])
                ->to($invoice)
                ->with(['Lines', 'Lines2'])
                ->copy();
        } catch (DeepCopyException $e) {
            $this->assertSame('no ref', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public function testDeepError()
    {
        $client = new DCClient($this->db);
        $client_id = $client->insert(['name' => 'John']);

        $quote = new DCQuote($this->db);

        $quote->insert(['ref' => 'q1', 'client_id' => $client_id, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote->loadAny();

        $invoice = new DCInvoice();
        $invoice->onHook(DeepCopy::HOOK_AFTER_COPY, function ($m) {
            if (!$m->get('ref')) {
                throw new \atk4\core\Exception('no ref');
            }
        });

        // total price should match
        $this->assertEquals(90.00, $quote->get('total'));

        $dc = new DeepCopy();

        $this->expectException(DeepCopyException::class);

        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['Lines' => ['qty']])
                ->to($invoice)
                ->with(['Lines'])
                ->copy();
        } catch (\atk4\data\Util\DeepCopyException $e) {
            $this->assertSame('Mandatory field value cannot be null', $e->getPrevious()->getMessage());

            throw $e;
        }
    }
}
