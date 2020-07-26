<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model\Aggregate;

class ModelAggregateTest extends \atk4\schema\PhpunitTestCase
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
    protected $aggregate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setDB($this->init_db);

        $invoice = new Model\Invoice($this->db);
        $invoice->getRef('client_id')->addTitle();

        $this->aggregate = new Aggregate($invoice);
        $this->aggregate->addField('client');
    }

    public function testGroupSelect()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], ['c' => ['expr' => 'count(*)', 'type' => 'integer']]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'c' => 2],
                ['client' => 'Zoe', 'client_id' => '2', 'c' => 1],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelect2()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => '2', 'amount' => 4.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelect3()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'min' => ['expr' => 'min([amount])', 'type' => 'money'],
            'max' => ['expr' => 'max([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'], // same as `s`, but reuse name `amount`
        ]);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'min' => 4.0, 'max' => 15.0, 'amount' => 19.0],
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'min' => 4.0, 'max' => 4.0, 'amount' => 4.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectExpr()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition()
    {
        $aggregate = $this->aggregate;
        $aggregate->master_model->addCondition('name', 'chair purchase');

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition2()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $aggregate->addCondition('double', '>', 10);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition3()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $aggregate->addCondition('double', 38);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupSelectCondition4()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);

        $aggregate->addExpression('double', ['[s]+[amount]', 'type' => 'money']);
        $aggregate->addCondition('client_id', 2);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => '2', 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupLimit()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);
        $aggregate->setLimit(1);

        $this->assertSame(
            [
                ['client' => 'Vinny', 'client_id' => '1', 'amount' => 19.0],
            ],
            $aggregate->export()
        );
    }

    public function testGroupLimit2()
    {
        $aggregate = $this->aggregate;

        $aggregate->groupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'money'],
        ]);
        $aggregate->setLimit(1, 1);

        $this->assertSame(
            [
                ['client' => 'Zoe', 'client_id' => '2', 'amount' => 4.0],
            ],
            $aggregate->export()
        );
    }
}
