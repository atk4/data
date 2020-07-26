<?php

declare(strict_types=1);

namespace atk4\data\tests;

class ModelUnionExprTest extends \atk4\schema\PhpunitTestCase
{
    /** @var Model\Transaction */
    protected $transaction;
    protected $client;
    
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

        $this->transaction = new Model\Transaction2($this->db);
        $this->client = new Model\Client($this->db, 'client');
                
        $this->client->hasMany('Payment', [Model\Payment::class]);
        $this->client->hasMany('Invoice', [Model\Invoice::class]);
    }

    public function testFieldExpr()
    {
        $transaction = $this->transaction;
        
        $e = $this->getEscapeChar();
        $this->assertSame(str_replace('"', $e, '"amount"'), $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'amount')])->render());
        $this->assertSame(str_replace('"', $e, '-"amount"'), $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'amount', '-[]')])->render());
        $this->assertSame(str_replace('"', $e, '-NULL'), $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'blah', '-[]')])->render());
    }

    public function testNestedQuery1()
    {
        $transaction = $this->transaction;

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"'),
            $transaction->getSubQuery(['name'])->render()
        );

        $this->assertSame(
            str_replace('"', $e, '(select "name" "name",-"amount" "amount" from "invoice" UNION ALL select "name" "name","amount" "amount" from "payment") "derivedTable"'),
            $transaction->getSubQuery(['name', 'amount'])->render()
        );

        $this->assertSame(
            str_replace('"', $e, '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"'),
            $transaction->getSubQuery(['name'])->render()
        );
    }

    /**
     * If field is not set for one of the nested model, instead of generating exception, NULL will be filled in.
     */
    public function testMissingField()
    {
        $transaction = $this->transaction;
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->addField('type');

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('`', $e, '(select (\'invoice\') `type`,-`amount` `amount` from `invoice` UNION ALL select NULL `type`,`amount` `amount` from `payment`) `derivedTable`'),
            $transaction->getSubQuery(['type', 'amount'])->render()
        );
    }

    public function testActions()
    {
        $transaction = $this->transaction;

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, 'select "name","amount" from (select "name" "name",-"amount" "amount" from "invoice" UNION ALL select "name" "name","amount" "amount" from "payment") "derivedTable"'),
            $transaction->action('select')->render()
        );

        $this->assertSame(
            str_replace('"', $e, 'select "name" from (select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"'),
            $transaction->action('field', ['name'])->render()
        );

        $this->assertSame(
            str_replace('"', $e, 'select sum("cnt") from (select count(*) "cnt" from "invoice" UNION ALL select count(*) "cnt" from "payment") "derivedTable"'),
            $transaction->action('count')->render()
        );

        $this->assertSame(
            str_replace('"', $e, 'select sum("val") from (select sum(-"amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "derivedTable"'),
            $transaction->action('fx', ['sum', 'amount'])->render()
        );
    }

    public function testActions2()
    {
        $transaction = $this->transaction;
        $this->assertSame(5, (int) $transaction->action('count')->getOne());
        $this->assertSame(-9.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());
    }

    public function testSubAction1()
    {
        $transaction = $this->transaction;
        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, '(select sum(-"amount") from "invoice" UNION ALL select sum("amount") from "payment") "derivedTable"'),
            $transaction->getSubAction('fx', ['sum', 'amount'])->render()
        );
    }

    public function testBasics()
    {
        $this->setDB($this->init_db);

        $client = $this->client;

        // There are total of 2 clients
        $this->assertSame(2, (int) $client->action('count')->getOne());

        // Client with ID=1 has invoices for 19
        $client->load(1);
        $this->assertSame(19.0, (float) $client->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());

        $transaction = new Model\Transaction2($this->db);
        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->hasMany('Transaction', new Model\Transaction2());

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'prepay', 'amount' => 10.0],
        ], $client->ref('Transaction')->export());
    }

    public function testGrouping1()
    {
        $transaction = $this->transaction;

        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, '(select "name" "name",sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable"'),
            $transaction->getSubQuery(['name', 'amount'])->render()
        );
    }

    public function testGrouping2()
    {
        $transaction = $this->transaction;

        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, 'select "name",sum("amount") "amount" from (select "name" "name",sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable" group by "name"'),
            $transaction->action('select', [['name', 'amount']])->render()
        );
    }

    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries.
     */
    public function testGrouping3()
    {
        $transaction = $this->transaction;
        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);
        $transaction->setOrder('name');

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -8.0],
            ['name' => 'full pay', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'table purchase', 'amount' => -15.0],
        ], $transaction->export());
    }

    /**
     * If a nested model has a field defined through expression, it should be still used in grouping. We should test this
     * with both expressions based off the fields and static expressions (such as "blah").
     */
    public function testSubGroupingByExpressions()
    {
        $transaction = $this->transaction;
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->nestedPayment->addExpression('type', '\'payment\'');
        $transaction->addField('type');

        $transaction->groupBy('type', ['amount' => ['sum([])', 'type' => 'money']]);

        $this->assertSame([
            ['type' => 'invoice', 'amount' => -23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $transaction->export(['type', 'amount']));
    }

    public function testReference()
    {
        $client = $this->client;
        $client->hasMany('tr', new Model\Transaction2());

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(-9.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $e = $this->getEscapeChar();
        $this->assertSame(
            str_replace('"', $e, 'select sum("val") from (select sum(-"amount") "val" from "invoice" where "client_id" = :a ' .
            'UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b) "derivedTable"'),
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()
        );
    }

    /**
     * Aggregation is supposed to work in theory, but MySQL uses "semi-joins" for this type of query which does not support UNION,
     * and therefore it complains about `client`.`id` field.
     *
     * See also: http://stackoverflow.com/questions/8326815/mysql-field-from-union-subselect#comment10267696_8326815
     */
    public function testFieldAggregate()
    {
        $this->client->hasMany('tr', new Model\Transaction2())
            ->addField('balance', ['field' => 'amount', 'aggregate' => 'sum']);

        $this->assertTrue(true); // fake assert
        //select "client"."id","client"."name",(select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = "client"."id" UNION ALL select sum("amount") "val" from "payment" where "client_id" = "client"."id") "derivedTable") "balance" from "client" where "client"."id" = 1 limit 0, 1
        //$c->load(1);
    }

    /**
     * Model's conditions can still be placed on the original field values.
     */
    public function testConditionOnMappedField()
    {
        $transaction = new Model\Transaction2($this->db);
        $transaction->nestedInvoice->addCondition('amount', 4);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }
}
