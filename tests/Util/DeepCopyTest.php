<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Util;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Atk4\Data\Util\DeepCopy;
use Atk4\Data\Util\DeepCopyException;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class DcClient extends Model
{
    public $table = 'client';

    protected function init(): void
    {
        parent::init();

        $this->addField('name');

        $this->hasMany('Invoices', ['model' => [DcInvoice::class]]);
        $this->hasMany('Quotes', ['model' => [DcQuote::class]]);
        $this->hasMany('Payments', ['model' => [DcPayment::class]]);
    }
}

class DcInvoice extends Model
{
    public $table = 'invoice';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('client_id', ['model' => [DcClient::class]]);

        $this->hasMany('Lines', ['model' => [DcInvoiceLine::class], 'theirField' => 'parent_id'])
            ->addField('total', ['aggregate' => 'sum', 'field' => 'total', 'type' => 'atk4_money']);

        $this->hasMany('Payments', ['model' => [DcPayment::class]])
            ->addField('paid', ['aggregate' => 'sum', 'field' => 'amount', 'type' => 'atk4_money']);

        $this->addExpression('due', ['expr' => '[total] - [paid]', 'type' => 'atk4_money']);

        $this->addField('ref');

        $this->addField('is_paid', ['type' => 'boolean', 'default' => false]);

        $this->onHookShort(DeepCopy::HOOK_AFTER_COPY, function (Model $s) {
            if (get_class($s) === static::class) {
                $this->set('ref', $this->get('ref') . '_copy');
            }
        });
    }
}

class DcQuote extends Model
{
    public $table = 'quote';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('client_id', ['model' => [DcClient::class]]);

        $this->hasMany('Lines', ['model' => [DcQuoteLine::class], 'theirField' => 'parent_id'])
            ->addField('total', ['aggregate' => 'sum', 'field' => 'total', 'type' => 'atk4_money']);

        $this->addField('ref');

        $this->addField('is_converted', ['type' => 'boolean', 'default' => false]);
    }
}

class DcInvoiceLine extends Model
{
    public $table = 'line';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('parent_id', ['model' => [DcInvoice::class]]);

        $this->addField('name');

        $this->addField('type', ['enum' => ['invoice', 'quote']]);
        $this->addCondition('type', '=', 'invoice');

        $this->addField('qty', ['type' => 'integer', 'nullable' => false]);
        $this->addField('price', ['type' => 'atk4_money']);
        $this->addField('vat', ['type' => 'float', 'default' => 0.21]);

        // total is calculated with VAT
        $this->addExpression('total', ['expr' => '[qty] * [price] * (1 + [vat])', 'type' => 'atk4_money']);
    }
}

class DcQuoteLine extends Model
{
    public $table = 'line';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('parent_id', ['model' => [DcQuote::class]]);

        $this->addField('name');

        $this->addField('type', ['enum' => ['invoice', 'quote']]);
        $this->addCondition('type', '=', 'quote');

        $this->addField('qty', ['type' => 'integer']);
        $this->addField('price', ['type' => 'atk4_money']);

        // total is calculated WITHOUT VAT
        $this->addExpression('total', ['expr' => '[qty] * [price]', 'type' => 'atk4_money']);
    }
}

class DcPayment extends Model
{
    public $table = 'payment';

    protected function init(): void
    {
        parent::init();

        $this->hasOne('client_id', ['model' => [DcClient::class]]);

        $this->hasOne('invoice_id', ['model' => [DcInvoice::class]]);

        $this->addField('amount', ['type' => 'atk4_money']);
    }
}

class DeepCopyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->createMigrator(new DcClient($this->db))->create();
        $this->createMigrator(new DcInvoice($this->db))->create();
        $this->createMigrator(new DcQuote($this->db))->create();
        $this->createMigrator(new DcInvoiceLine($this->db))->create();
        $this->createMigrator(new DcPayment($this->db))->create();
    }

    public function testBasic(): void
    {
        $client = new DcClient($this->db);
        $clientId = $client->insert(['name' => 'John']);

        $quote = new DcQuote($this->db);

        $quote->insert(['ref' => 'q1', 'client_id' => $clientId, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote = $quote->loadOne();

        // total price should match
        self::assertSame(90.0, $quote->get('total'));

        $dc = new DeepCopy();
        $invoice = $dc
            ->from($quote)
            ->to(new DcInvoice())
            ->with(['Lines'])
            ->copy();

        // price now will be with VAT
        self::assertSame('q1', $invoice->get('ref'));
        self::assertSame(108.9, $invoice->get('total'));
        self::assertSame(1, $invoice->getId());

        // Note that we did not specify that 'client_id' should be copied, so same value here
        self::assertSame($quote->get('client_id'), $invoice->get('client_id'));
        self::assertSame('John', $invoice->ref('client_id')->get('name'));

        // now to add payment for the invoice. Payment originates from the same client as noted on the invoice
        $invoice->ref('Payments')->insert(['amount' => $invoice->get('total') - 5, 'client_id' => $invoice->get('client_id')]);

        $invoice->reload();

        // now that invoice is mostly paid, due amount will reflect that
        self::assertSame(5.0, $invoice->get('due'));

        // Next we copy invoice into simply a new record. Duplicate. However this time we will also duplicate payments,
        // and client. Because Payment references client too, we need to duplicate that one also, this way new record
        // structure will not be related to any existing records.
        $dc = new DeepCopy();
        $invoiceCopy = $dc
            ->from($invoice)
            ->to(new DcInvoice())
            ->with(['Lines', 'client_id', 'Payments' => ['client_id']])
            ->copy();

        // Invoice copy receives a new ID
        self::assertNotSame($invoice->getId(), $invoiceCopy->getId());
        self::assertSame('q1_copy', $invoiceCopy->get('ref'));

        // ..however the due amount is the same - 5
        self::assertSame(5.0, $invoiceCopy->get('due'));

        // ..client record was created in the process
        self::assertNotSame($invoiceCopy->get('client_id'), $invoice->get('client_id'));

        // ..but he is still called John
        self::assertSame('John', $invoiceCopy->ref('client_id')->get('name'));

        // finally, the client_id used for newly created payment and new invoice correspond
        self::assertSame($invoiceCopy->get('client_id'), $invoiceCopy->ref('Payments')->loadAny()->get('client_id'));

        // the final test is to copy client entirely!

        $dc = new DeepCopy();
        $client3 = $dc
            ->from((new DcClient($this->db))->load(1))
            ->to(new DcClient())
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
        self::assertSame(3, $client3->getId());

        // We should have one of each records for this new client
        self::assertSame(1, $client3->ref('Invoices')->executeCountQuery());
        self::assertSame(1, $client3->ref('Quotes')->executeCountQuery());
        self::assertSame(1, $client3->ref('Payments')->executeCountQuery());

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::markTestIncomplete('TODO MSSQL: Cannot perform an aggregate function on an expression containing an aggregate or a subquery');
        }

        // We created invoice for 90 for client1, so after copying it should still be 90
        self::assertSame(90.0, (float) $client3->ref('Quotes')->action('fx', ['sum', 'total'])->getOne());

        // The total of the invoice we copied, should remain, it's calculated based on lines
        self::assertSame(108.9, (float) $client3->ref('Invoices')->action('fx', ['sum', 'total'])->getOne());

        // Payments by this clients should also be copied correctly
        self::assertSame(103.9, (float) $client3->ref('Payments')->action('fx', ['sum', 'amount'])->getOne());

        // If copied payments are properly allocated against copied invoices, then due amount will be 5
        self::assertSame(5.0, (float) $client3->ref('Invoices')->action('fx', ['sum', 'due'])->getOne());
    }

    public function testError(): void
    {
        $client = new DcClient($this->db);
        $clientId = $client->insert(['name' => 'John']);

        $quote = new DcQuote($this->db);
        $quote->hasMany('Lines2', ['model' => [DcQuoteLine::class], 'theirField' => 'parent_id']);

        $quote->insert(['ref' => 'q1', 'client_id' => $clientId, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote = $quote->loadOne();

        $invoice = new DcInvoice();
        $invoice->onHook(DeepCopy::HOOK_AFTER_COPY, static function (Model $m) {
            if (!$m->get('ref')) {
                throw new \Exception('no ref');
            }
        });

        // total price should match
        self::assertSame(90.0, $quote->get('total'));

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
            self::assertSame('no ref', $e->getPrevious()->getMessage());

            throw $e;
        }
    }

    public function testDeepError(): void
    {
        $client = new DcClient($this->db);
        $clientId = $client->insert(['name' => 'John']);

        $quote = new DcQuote($this->db);

        $quote->insert(['ref' => 'q1', 'client_id' => $clientId, 'Lines' => [
            ['name' => 'tools', 'qty' => 5, 'price' => 10],
            ['name' => 'work', 'qty' => 1, 'price' => 40],
        ]]);
        $quote = $quote->loadOne();

        $invoice = new DcInvoice();
        $invoice->onHook(DeepCopy::HOOK_AFTER_COPY, static function (Model $m) {
            if (!$m->get('ref')) {
                throw new \Exception('no ref');
            }
        });

        // total price should match
        self::assertSame(90.0, $quote->get('total'));

        $dc = new DeepCopy();

        $this->expectException(DeepCopyException::class);
        try {
            $invoice = $dc
                ->from($quote)
                ->excluding(['Lines' => ['qty']])
                ->to($invoice)
                ->with(['Lines'])
                ->copy();
        } catch (DeepCopyException $e) {
            self::assertSame('Must not be null', $e->getPrevious()->getMessage());

            throw $e;
        }
    }
}
