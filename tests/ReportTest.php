<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model\AggregateModel;
use Atk4\Data\Schema\TestCase;

class ReportTest extends TestCase
{
    /** @var array */
    private $init_db =
        [
            'client' => [
                // allow of migrator to create all columns
                ['name' => 'Vinny', 'surname' => null, 'order' => null],
                ['name' => 'Zoe'],
            ],
            'invoice' => [
                ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
                ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
                ['client_id' => 2, 'name' => 'chair purchase', 'amount' => 4.0],
            ],
            'payment' => [
                ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
                ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
            ],
        ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDb($this->init_db);
    }

    protected function createInvoiceAggregate(): AggregateModel
    {
        $invoice = new Model\Invoice($this->db);
        $invoice->getRef('client_id')->addTitle();
        $invoiceAggregate = new AggregateModel($invoice);
        $invoiceAggregate->addField('client');

        return $invoiceAggregate;
    }

    public function testAliasGroupSelect(): void
    {
        $invoiceAggregate = $this->createInvoiceAggregate();

        $invoiceAggregate->groupBy(['client_id'], ['c' => ['count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 'c' => 2],
                ['client' => 'Zoe', 'client_id' => 2, 'c' => 1],
            ],
            $invoiceAggregate->setOrder('client_id', 'asc')->export()
        );
    }
}
