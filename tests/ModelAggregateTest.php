<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model\Aggregate;
use Atk4\Data\Model\Scope;
use Atk4\Data\Model\Scope\Condition;

class ModelAggregateTest extends \Atk4\Schema\PhpunitTestCase
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
        $this->setDB($this->init_db);
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

    public function testGroupBy()
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

    public function testGroupSelect()
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

    public function testGroupSelect2()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelect3()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'min' => ['expr' => 'min([amount])', 'type' => 'money'],
            'max' => ['expr' => 'max([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'], // same as `s`, but reuse name `amount`
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'min' => 4.0, 'max' => 15.0, 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'min' => 4.0, 'max' => 4.0, 'amount' => 4.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelectExpr()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelectCondition()
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->baseModel->addCondition('name', 'chair purchase');

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupSelectCondition2()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $aggregate->addCondition('double', '>', 10);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition3()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $aggregate->addCondition('double', 38);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition4()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $aggregate->addCondition('client_id', 2);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectScope()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
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

    public function testGroupOrder()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->setOrder('client_id', 'asc');

        $this->assertSameSql(
            'select (select "name" from "client" "c" where "id" = "invoice"."client_id") "client","invoice"."client_id",sum("invoice"."amount") "amount" from "invoice" group by "invoice"."client_id" order by "invoice"."client_id"',
            $aggregate->action('select')->render()
        );
    }

    public function testGroupLimit()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);
        $aggregate->setLimit(1);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => 1, 'amount' => 19.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupLimit2()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);
        $aggregate->setLimit(1, 1);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
            ],
            $aggregate->setOrder('client_id', 'asc')->export()
        );
    }

    public function testGroupCount()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $this->assertSameSql(
            'select count(*) from ((select 1 from "invoice" group by "client_id")) der',
            $aggregate->action('count')->render()
        );
    }

    public function testAggregateFieldExpression()
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->groupBy(['abc'], [
            'xyz' => ['expr' => 'sum([amount])'],
        ]);

        $this->assertSameSql(
            'select (select "name" from "client" "c" where "id" = "invoice"."client_id") "client","invoice"."abc",sum("invoice"."amount") "xyz" from "invoice" group by abc',
            $aggregate->action('select')->render()
        );
    }
}
