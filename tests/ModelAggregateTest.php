<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model\Aggregate;
use Atk4\Data\Model\Scope;
use Atk4\Data\Model\Scope\Condition;
use Atk4\Data\Schema\TestCase;

class ModelAggregateTest extends TestCase
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

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDb($this->init_db);
    }

    protected function createInvoice(): Model\Invoice
    {
        $invoice = new Model\Invoice($this->db);
        $invoice->getRef('client_id')->addTitle();

        return $invoice;
    }

    protected function createInvoiceAggregate(): Aggregate
    {
        return $this->createInvoice()->withAggregateField('client');
    }

    public function testGroupBy(): void
    {
        $invoiceAggregate = $this->createInvoice()->groupBy(['client_id'], ['c' => ['expr' => 'count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client_id' => 1, 'c' => 2],
                ['client_id' => 2, 'c' => 1],
            ],
            $invoiceAggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelect(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], ['c' => ['expr' => 'count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 'c' => 2],
                ['client' => 'Zoe', 'client_id' => 2, 'c' => 1],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelect2(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelect3(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'min' => ['expr' => 'min([amount])', 'type' => 'atk4_money'],
            'max' => ['expr' => 'max([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'], // same as `s`, but reuse name `amount`
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'min' => 4.0, 'max' => 15.0, 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'min' => 4.0, 'max' => 4.0, 'amount' => 4.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelectExpr(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'atk4_money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelectCondition(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->baseModel->addCondition('name', 'chair purchase');

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'atk4_money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelectCondition2(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'atk4_money']);
        $aggregate->addCondition('double', '>', 10);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition3(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'atk4_money']);
        $aggregate->addCondition('double', 38);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition4(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'atk4_money']);
        $aggregate->addCondition('client_id', 2);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectScope(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $scope = Scope::createAnd(new Condition('client_id', 2), new Condition('amount', 4));

        $aggregate->addCondition($scope);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupOrder(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $aggregate->setOrder('client_id', 'asc');

        $this->assertSameSql(
            'select (select "name" from "client" "_c_2bfe9d72a4aa" where "id" = "invoice"."client_id") "client", "invoice"."client_id", sum("amount") "amount" from "invoice" group by "client_id" order by "invoice"."client_id"',
            $aggregate->action('select')->render()[0]
        );
    }

    public function testGroupLimit(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        $aggregate->setLimit(1);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 'amount' => 19.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupLimit2(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        $aggregate->setLimit(1, 1);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupCount(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $this->assertSameSql(
            'select count(*) from ((select 1 from "invoice" group by "client_id")) der',
            $aggregate->action('count')->render()[0]
        );
    }

    public function testAggregateFieldExpression(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['abc'], [
            'xyz' => ['expr' => 'sum([amount])'],
        ]);

        $this->assertSameSql(
            'select (select "name" from "client" "_c_2bfe9d72a4aa" where "id" = "invoice"."client_id") "client", "invoice"."abc", sum("amount") "xyz" from "invoice" group by abc',
            $aggregate->action('select')->render()[0]
        );
    }
}
