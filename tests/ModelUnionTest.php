<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Dsql\Expression;
use Doctrine\DBAL\Platforms\OraclePlatform;

class ModelUnionTest extends \Atk4\Schema\PhpunitTestCase
{
    /** @var Model\Client */
    protected $client;
    /** @var Model\Transaction */
    protected $transaction;
    /** @var Model\Transaction */
    protected $subtractInvoiceTransaction;

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
        $this->setDB($this->init_db);

        $this->client = $this->createClient($this->db);
        $this->transaction = $this->createTransaction($this->db);
        $this->subtractInvoiceTransaction = $this->createSubtractInvoiceTransaction($this->db);
    }

    protected function tearDown(): void
    {
        $this->client = null;
        $this->transaction = null;
        $this->subtractInvoiceTransaction = null;

        parent::tearDown();
    }

    protected function createTransaction($persistence = null)
    {
        return new Model\Transaction($persistence);
    }

    protected function createSubtractInvoiceTransaction($persistence = null)
    {
        return new Model\Transaction($persistence, ['subtractInvoice' => true]);
    }

    protected function createClient($persistence = null)
    {
        $client = new Model\Client($this->db);

        $client->hasMany('Payment', ['model' => [Model\Payment::class]]);
        $client->hasMany('Invoice', ['model' => [Model\Invoice::class]]);

        return $client;
    }

    public function testFieldExpr()
    {
        $transaction = clone $this->subtractInvoiceTransaction;

        $this->assertSameSql('"amount"', $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'amount')])->render());
        $this->assertSameSql('-"amount"', $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'amount', '-[]')])->render());
        $this->assertSameSql('-NULL', $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'blah', '-[]')])->render());
    }

    public function testNestedQuery1()
    {
        $transaction = clone $this->transaction;

        $this->assertSameSql(
            '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"',
            $transaction->getSubQuery(['name'])->render()
        );

        $this->assertSameSql(
            '(select "name" "name","amount" "amount" from "invoice" UNION ALL select "name" "name","amount" "amount" from "payment") "derivedTable"',
            $transaction->getSubQuery(['name', 'amount'])->render()
        );

        $this->assertSameSql(
            '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"',
            $transaction->getSubQuery(['name'])->render()
        );
    }

    /**
     * If field is not set for one of the nested model, instead of generating exception, NULL will be filled in.
     */
    public function testMissingField()
    {
        $transaction = clone $this->transaction;
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->addField('type');

        $this->assertSameSql(
            '(select (\'invoice\') "type","amount" "amount" from "invoice" UNION ALL select NULL "type","amount" "amount" from "payment") "derivedTable"',
            $transaction->getSubQuery(['type', 'amount'])->render()
        );
    }

    public function testActions()
    {
        $transaction = clone $this->transaction;

        $this->assertSameSql(
            'select "name","amount" from (select "name" "name","amount" "amount" from "invoice" UNION ALL select "name" "name","amount" "amount" from "payment") "derivedTable"',
            $transaction->action('select')->render()
        );

        $this->assertSameSql(
            'select "name" from (select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"',
            $transaction->action('field', ['name'])->render()
        );

        $this->assertSameSql(
            'select sum("cnt") from (select count(*) "cnt" from "invoice" UNION ALL select count(*) "cnt" from "payment") "derivedTable"',
            $transaction->action('count')->render()
        );

        $this->assertSameSql(
            'select sum("val") from (select sum("amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "derivedTable"',
            $transaction->action('fx', ['sum', 'amount'])->render()
        );

        $transaction = clone $this->subtractInvoiceTransaction;

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "derivedTable"',
            $transaction->action('fx', ['sum', 'amount'])->render()
        );
    }

    public function testActions2()
    {
        $transaction = clone $this->transaction;
        $this->assertSame(5, (int) $transaction->action('count')->getOne());
        $this->assertSame(37.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());

        $transaction = clone $this->subtractInvoiceTransaction;
        $this->assertSame(-9.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());
    }

    public function testSubAction1()
    {
        $transaction = clone $this->subtractInvoiceTransaction;

        $this->assertSameSql(
            '(select sum(-"amount") from "invoice" UNION ALL select sum("amount") from "payment") "derivedTable"',
            $transaction->getSubAction('fx', ['sum', 'amount'])->render()
        );
    }

    public function testBasics()
    {
        $client = clone $this->client;

        // There are total of 2 clients
        $this->assertSame(2, (int) $client->action('count')->getOne());

        // Client with ID=1 has invoices for 19
        $client->load(1);

        $this->assertSame(19.0, (float) $client->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());

        $transaction = clone $this->transaction;

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => 4.0],
            ['name' => 'table purchase', 'amount' => 15.0],
            ['name' => 'chair purchase', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->hasMany('Transaction', $transaction);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => 4.0],
            ['name' => 'table purchase', 'amount' => 15.0],
            ['name' => 'prepay', 'amount' => 10.0],
        ], $client->ref('Transaction')->export());

        $client = clone $this->client;

        $client->load(1);

        $transaction = clone $this->subtractInvoiceTransaction;

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->hasMany('Transaction', $transaction);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'prepay', 'amount' => 10.0],
        ], $client->ref('Transaction')->export());
    }

    public function testGrouping1()
    {
        $transaction = clone $this->transaction;

        $transaction->groupBy('name', ['amount' => ['sum([amount])', 'type' => 'money']]);

        $this->assertSameSql(
            '(select "name" "name",sum("amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable"',
            $transaction->getSubQuery(['name', 'amount'])->render()
        );

        $transaction = clone $this->subtractInvoiceTransaction;

        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);

        $this->assertSameSql(
            '(select "name" "name",sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable"',
            $transaction->getSubQuery(['name', 'amount'])->render()
        );
    }

    public function testGrouping2()
    {
        $transaction = clone $this->transaction;

        $transaction->groupBy('name', ['amount' => ['sum([amount])', 'type' => 'money']]);

        $this->assertSameSql(
            'select "name",sum("amount") "amount" from (select "name" "name",sum("amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable" group by "name"',
            $transaction->action('select', [['name', 'amount']])->render()
        );

        $transaction = clone $this->subtractInvoiceTransaction;

        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'money']]);

        $this->assertSameSql(
            'select "name",sum("amount") "amount" from (select "name" "name",sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name",sum("amount") "amount" from "payment" group by "name") "derivedTable" group by "name"',
            $transaction->action('select', [['name', 'amount']])->render()
        );
    }

    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries.
     */
    public function testGrouping3()
    {
        $transaction = clone $this->transaction;
        $transaction->groupBy('name', ['amount' => ['sum([amount])', 'type' => 'money']]);
        $transaction->setOrder('name');

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => 8.0],
            ['name' => 'full pay', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'table purchase', 'amount' => 15.0],
        ], $transaction->export());

        $transaction = clone $this->subtractInvoiceTransaction;
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
        if ($this->getDatabasePlatform() instanceof OraclePlatform) { // TODO
            $this->markTestIncomplete('TODO - for some reasons Oracle does not accept the query');
        }

        $transaction = clone $this->transaction;
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->nestedPayment->addExpression('type', '\'payment\'');
        $transaction->addField('type');

        $transaction->groupBy('type', ['amount' => ['sum([amount])', 'type' => 'money']]);

        $this->assertSame([
            ['type' => 'invoice', 'amount' => 23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $transaction->export(['type', 'amount']));

        $transaction = clone $this->subtractInvoiceTransaction;
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
        $client = clone $this->client;
        $client->hasMany('tr', $this->createTransaction());

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(29.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = :a ' .
            'UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b) "derivedTable"',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()
        );

        $client = clone $this->client;
        $client->hasMany('tr', $this->createSubtractInvoiceTransaction());

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(-9.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" where "client_id" = :a ' .
                'UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b) "derivedTable"',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()
        );
    }

    /**
     * Aggregation is supposed to work in theory, but MySQL uses "semi-joins" for this type of query which does not support UNION,
     * and therefore it complains about "client"."id" field.
     *
     * See also: http://stackoverflow.com/questions/8326815/mysql-field-from-union-subselect#comment10267696_8326815
     */
    public function testFieldAggregate()
    {
        $client = clone $this->client;
        $client->hasMany('tr', $this->createTransaction())
            ->addField('balance', ['field' => 'amount', 'aggregate' => 'sum']);

        $this->assertTrue(true); // fake assert
        //select "client"."id","client"."name",(select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = "client"."id" UNION ALL select sum("amount") "val" from "payment" where "client_id" = "client"."id") "derivedTable") "balance" from "client" where "client"."id" = 1 limit 0, 1
        //$c->load(1);
    }

    public function testConditionOnUnionField()
    {
        $transaction = clone $this->subtractInvoiceTransaction;
        $transaction->addCondition('amount', '<', 0);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'table purchase', 'amount' => -15.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModelField()
    {
        $transaction = clone $this->subtractInvoiceTransaction;
        $transaction->addCondition('client_id', '>', 1);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }

    public function testConditionForcedOnNestedModels1()
    {
        $transaction = clone $this->subtractInvoiceTransaction;
        $transaction->addCondition('amount', '>', 5, true);

        $this->assertSame([
            ['name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    public function testConditionForcedOnNestedModels2()
    {
        $transaction = clone $this->subtractInvoiceTransaction;
        $transaction->addCondition('amount', '<', -10, true);

        $this->assertSame([
            ['name' => 'table purchase', 'amount' => -15.0],
        ], $transaction->export());
    }

    public function testConditionExpression()
    {
        $transaction = clone $this->subtractInvoiceTransaction;
        $transaction->addCondition($transaction->expr('{} > 5', ['amount']));

        $this->assertSame([
            ['name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    /**
     * Model's conditions can still be placed on the original field values.
     */
    public function testConditionOnMappedField()
    {
        $transaction = clone $this->subtractInvoiceTransaction;
        $transaction->nestedInvoice->addCondition('amount', 4);

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'chair purchase', 'amount' => -4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }
}
