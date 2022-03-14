<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model\AggregateModel;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class ModelUnionTest extends TestCase
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

    protected function createTransaction(): Model\Transaction
    {
        return new Model\Transaction($this->db);
    }

    protected function createSubtractInvoiceTransaction(): Model\Transaction
    {
        return new Model\Transaction($this->db, ['subtractInvoice' => true]);
    }

    protected function createClient(): Model\Client
    {
        $client = new Model\Client($this->db);

        $client->hasMany('Payment', ['model' => [Model\Payment::class]]);
        $client->hasMany('Invoice', ['model' => [Model\Invoice::class]]);

        return $client;
    }

    public function testFieldExpr(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameSql('"amount"', $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'amount')])->render()[0]);
        $this->assertSameSql('-"amount"', $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'amount', '-[]')])->render()[0]);
        $this->assertSameSql('-NULL', $transaction->expr('[]', [$transaction->getFieldExpr($transaction->nestedInvoice, 'blah', '-[]')])->render()[0]);
    }

    public function testNestedQuery1(): void
    {
        $transaction = $this->createTransaction();

        $this->assertSameSql(
            'select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment"',
            $transaction->getSubQuery(['name'])->render()[0]
        );

        $this->assertSameSql(
            'select "name" "name", "amount" "amount" from "invoice" UNION ALL select "name" "name", "amount" "amount" from "payment"',
            $transaction->getSubQuery(['name', 'amount'])->render()[0]
        );

        $this->assertSameSql(
            'select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment"',
            $transaction->getSubQuery(['name'])->render()[0]
        );
    }

    /**
     * If field is not set for one of the nested model, instead of generating exception, NULL will be filled in.
     */
    public function testMissingField(): void
    {
        $transaction = $this->createTransaction();
        $transaction->nestedInvoice->addExpression('type', ['expr' => '\'invoice\'']);
        $transaction->addField('type');

        $this->assertSameSql(
            'select (\'invoice\') "type", "amount" "amount" from "invoice" UNION ALL select NULL "type", "amount" "amount" from "payment"',
            $transaction->getSubQuery(['type', 'amount'])->render()[0]
        );
    }

    public function testActions(): void
    {
        $transaction = $this->createTransaction();

        $this->assertSameSql(
            'select "client_id", "name", "amount" from (select "client_id" "client_id", "name" "name", "amount" "amount" from "invoice" UNION ALL select "client_id" "client_id", "name" "name", "amount" "amount" from "payment") "_tu"',
            $transaction->action('select')->render()[0]
        );

        $this->assertSameSql(
            'select "name" from (select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "_tu"',
            $transaction->action('field', ['name'])->render()[0]
        );

        $this->assertSameSql(
            'select sum("cnt") from (select count(*) "cnt" from "invoice" UNION ALL select count(*) "cnt" from "payment") "_tu"',
            $transaction->action('count')->render()[0]
        );

        $this->assertSameSql(
            'select sum("val") from (select sum("amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "_tu"',
            $transaction->action('fx', ['sum', 'amount'])->render()[0]
        );

        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "_tu"',
            $transaction->action('fx', ['sum', 'amount'])->render()[0]
        );
    }

    public function testActions2(): void
    {
        $transaction = $this->createTransaction();
        $this->assertSame('5', $transaction->action('count')->getOne());
        $this->assertSame(37.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());

        $transaction = $this->createSubtractInvoiceTransaction();
        $this->assertSame(-9.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());
    }

    public function testSubAction1(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameSql(
            'select sum(-"amount") from "invoice" UNION ALL select sum("amount") from "payment"',
            $transaction->getSubAction('fx', ['sum', 'amount'])->render()[0]
        );
    }

    public function testBasics(): void
    {
        $client = $this->createClient();

        // There are total of 2 clients
        $this->assertSame('2', $client->action('count')->getOne());

        // Client with ID=1 has invoices for 19
        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());

        $transaction = $this->createTransaction();

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => 4.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is UnionModel Model
        $client->hasMany('Transaction', ['model' => $transaction]);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $client->load(1)->ref('Transaction')->export());

        $client = $this->createClient();

        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is UnionModel Model
        $client->hasMany('Transaction', ['model' => $transaction]);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $client->load(1)->ref('Transaction')->export());
    }

    public function testGrouping1(): void
    {
        $transaction = $this->createTransaction();

        $transactionAggregate = new AggregateModel($transaction);
        $transactionAggregate->setGroupBy(['name'], [
            'amount' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
        ]);

        $this->assertSameSql(
            'select "name", sum("amount") "amount" from (select "client_id", "name", "amount" from (select "client_id" "client_id", "name" "name", "amount" "amount" from "invoice" UNION ALL select "client_id" "client_id", "name" "name", "amount" "amount" from "payment") "_tu") "_tm" group by "name"',
            $transactionAggregate->action('select', [['name', 'amount']])->render()[0]
        );

        $transaction = $this->createSubtractInvoiceTransaction();

        $transactionAggregate = new AggregateModel($transaction);
        $transactionAggregate->setGroupBy(['name'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $this->assertSameSql(
            'select "name", sum("amount") "amount" from (select "client_id", "name", "amount" from (select "client_id" "client_id", "name" "name", -"amount" "amount" from "invoice" UNION ALL select "client_id" "client_id", "name" "name", "amount" "amount" from "payment") "_tu") "_tm" group by "name"',
            $transactionAggregate->action('select', [['name', 'amount']])->render()[0]
        );
    }

    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries.
     */
    public function testGrouping2(): void
    {
        $transaction = $this->createTransaction();
        $transaction->removeField('client_id');
        // TODO enable later, test failing with MSSQL $transaction->setOrder('name');
        $transactionAggregate = new AggregateModel($transaction);
        $transactionAggregate->setGroupBy(['name'], [
            'amount' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
        ]);
        $transactionAggregate->setOrder('name');

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => 8.0],
            ['name' => 'full pay', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'table purchase', 'amount' => 15.0],
        ], $transactionAggregate->export());

        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->removeField('client_id');
        // TODO enable later, test failing with MSSQL $transaction->setOrder('name');
        $transactionAggregate = new AggregateModel($transaction);
        $transactionAggregate->setGroupBy(['name'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        $transactionAggregate->setOrder('name');

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => -8.0],
            ['name' => 'full pay', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'table purchase', 'amount' => -15.0],
        ], $transactionAggregate->export());
    }

    /**
     * If a nested model has a field defined through expression, it should be still used in grouping. We should test this
     * with both expressions based off the fields and static expressions (such as "blah").
     */
    public function testSubGroupingByExpressions(): void
    {
        $transaction = $this->createTransaction();
        $transaction->nestedInvoice->addExpression('type', ['expr' => '\'invoice\'']);
        $transaction->nestedPayment->addExpression('type', ['expr' => '\'payment\'']);
        $transaction->addField('type');

        $transactionAggregate = new AggregateModel($transaction);
        $transactionAggregate->setGroupBy(['type'], [
            'amount' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
        ]);

        // TODO subselects should not select "client" and "name" fields
        $this->assertSameSql(
            'select "type", sum("amount") "amount" from (select "client_id", "name", "amount", "type" from (select "client_id" "client_id", "name" "name", "amount" "amount", (\'invoice\') "type" from "invoice" UNION ALL select "client_id" "client_id", "name" "name", "amount" "amount", (\'payment\') "type" from "payment") "_tu") "_tm" group by "type"',
            $transactionAggregate->action('select')->render()[0]
        );

        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            $this->markTestIncomplete('TODO MSSQL: Constant value column seem not supported (Invalid column name \'type\')');
        }

        $this->assertSameExportUnordered([
            ['type' => 'invoice', 'amount' => 23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $transactionAggregate->export(['type', 'amount']));

        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->nestedInvoice->addExpression('type', ['expr' => '\'invoice\'']);
        $transaction->nestedPayment->addExpression('type', ['expr' => '\'payment\'']);
        $transaction->addField('type');

        $transactionAggregate = new AggregateModel($transaction);
        $transactionAggregate->setGroupBy(['type'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);

        $this->assertSameExportUnordered([
            ['type' => 'invoice', 'amount' => -23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $transactionAggregate->export(['type', 'amount']));
    }

    public function testReference(): void
    {
        $client = $this->createClient();
        $client->hasMany('tr', ['model' => $this->createTransaction()]);

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());

        // TODO aggregated fields are pushdown, but where condition is not
        // I belive the fields pushdown is even wrong as not every aggregated result produces same result when aggregated again
        // then fix also self::testFieldAggregate()
        $this->assertTrue(true);

        return;
        // @phpstan-ignore-next-line
        $this->assertSame(29.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = :a UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b)',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()[0]
        );

        $client = $this->createClient();
        $client->hasMany('tr', ['model' => $this->createSubtractInvoiceTransaction()]);

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(-9.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" where "client_id" = :a UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b)',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()[0]
        );
    }

    /**
     * Aggregation is supposed to work in theory, but MySQL uses "semi-joins" for this type of query which does not support UNION,
     * and therefore it complains about "client"."id" field.
     *
     * See also: http://stackoverflow.com/questions/8326815/mysql-field-from-union-subselect#comment10267696_8326815
     */
    public function testFieldAggregate(): void
    {
        $client = $this->createClient();
        $client->hasMany('tr', ['model' => $this->createTransaction()])
            ->addField('balance', ['field' => 'amount', 'aggregate' => 'sum']);

        // TODO some fields are pushdown, but some not, same issue as in self::testReference()
        $this->assertTrue(true);

        return;
        // @phpstan-ignore-next-line
        $this->assertSameSql(
            'select "client"."id", "client"."name", (select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = "client"."id" UNION ALL select sum("amount") "val" from "payment" where "client_id" = "client"."id") "_tu") "balance" from "client" where "client"."id" = 1 limit 0, 1',
            $client->load(1)->action('select')->render()[0]
        );
    }

    public function testConditionOnUnionField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '<', 0);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModelField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('client_id', '>', 1);

        $this->assertSameExportUnordered([
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }

    public function testConditionForcedOnNestedModels1(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '>', 5, true);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    public function testConditionForcedOnNestedModels2(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '<', -10, true);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
        ], $transaction->export());
    }

    public function testConditionExpression(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition($transaction->expr('{} > 5', ['amount']));

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    /**
     * Model's conditions can still be placed on the original field values.
     */
    public function testConditionOnMappedField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->nestedInvoice->addCondition('amount', 4);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }
}
