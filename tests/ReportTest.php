<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model\Aggregate;

class ReportTest extends \atk4\schema\PhpunitTestCase
{
    /** @var array */
    private $init_db =
        [
            'client' => [
                ['name' => 'Vinny'],
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

    /** @var Aggregate */
    protected $invoiceAggregate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDB($this->init_db);

        $invoice = new Model\Invoice($this->db);
        $invoice->getRef('client_id')->addTitle();
        $this->invoiceAggregate = new Aggregate($invoice);
        $this->invoiceAggregate->addField('client');
    }

    public function testAliasGroupSelect()
    {
        $invoiceAggregate = clone $this->invoiceAggregate;

        $invoiceAggregate->groupBy(['client_id'], ['c' => ['count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'c' => 2],
                ['client' => 'Zoe', 'client_id' => '2', 'c' => 1],
            ],
            $invoiceAggregate->setOrder('client_id', 'asc')->export()
        );
    }
}
