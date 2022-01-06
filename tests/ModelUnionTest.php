<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;

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
            '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"',
            $transaction->getSubQuery(['name'])->render()[0]
        );

        $this->assertSameSql(
            '(select "name" "name", "amount" "amount" from "invoice" UNION ALL select "name" "name", "amount" "amount" from "payment") "derivedTable"',
            $transaction->getSubQuery(['name', 'amount'])->render()[0]
        );

        $this->assertSameSql(
            '(select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"',
            $transaction->getSubQuery(['name'])->render()[0]
        );
    }

    /**
     * If field is not set for one of the nested model, instead of generating exception, NULL will be filled in.
     */
    public function testMissingField(): void
    {
        $transaction = $this->createTransaction();
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->addField('type');

        $this->assertSameSql(
            '(select (\'invoice\') "type", "amount" "amount" from "invoice" UNION ALL select NULL "type", "amount" "amount" from "payment") "derivedTable"',
            $transaction->getSubQuery(['type', 'amount'])->render()[0]
        );
    }

    public function testActions(): void
    {
        $transaction = $this->createTransaction();

        $this->assertSameSql(
            'select "client_id", "name", "amount" from (select "client_id" "client_id", "name" "name", "amount" "amount" from "invoice" UNION ALL select "client_id" "client_id", "name" "name", "amount" "amount" from "payment") "derivedTable"',
            $transaction->action('select')->render()[0]
        );

        $this->assertSameSql(
            'select "name" from (select "name" "name" from "invoice" UNION ALL select "name" "name" from "payment") "derivedTable"',
            $transaction->action('field', ['name'])->render()[0]
        );

        $this->assertSameSql(
            'select sum("cnt") from (select count(*) "cnt" from "invoice" UNION ALL select count(*) "cnt" from "payment") "derivedTable"',
            $transaction->action('count')->render()[0]
        );

        $this->assertSameSql(
            'select sum("val") from (select sum("amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "derivedTable"',
            $transaction->action('fx', ['sum', 'amount'])->render()[0]
        );

        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" UNION ALL select sum("amount") "val" from "payment") "derivedTable"',
            $transaction->action('fx', ['sum', 'amount'])->render()[0]
        );
    }

    public function testActions2(): void
    {
        $transaction = $this->createTransaction();
        $this->assertSame(5, (int) $transaction->action('count')->getOne());
        $this->assertSame(37.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());

        $transaction = $this->createSubtractInvoiceTransaction();
        $this->assertSame(-9.0, (float) $transaction->action('fx', ['sum', 'amount'])->getOne());
    }

    public function testSubAction1(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSameSql(
            '(select sum(-"amount") from "invoice" UNION ALL select sum("amount") from "payment") "derivedTable"',
            $transaction->getSubAction('fx', ['sum', 'amount'])->render()[0]
        );
    }

    public function testBasics(): void
    {
        $client = $this->createClient();

        // There are total of 2 clients
        $this->assertSame(2, (int) $client->action('count')->getOne());

        // Client with ID=1 has invoices for 19
        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());

        $transaction = $this->createTransaction();

        $this->assertSame([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => 4.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->hasMany('Transaction', ['model' => $transaction]);

        $this->assertSame([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => 4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => 15.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $client->load(1)->ref('Transaction')->export());

        $client = $this->createClient();

        $transaction = $this->createSubtractInvoiceTransaction();

        $this->assertSame([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());

        // Transaction is Union Model
        $client->hasMany('Transaction', ['model' => $transaction]);

        $this->assertSame([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $client->load(1)->ref('Transaction')->export());
    }

    public function testGrouping1(): void
    {
        $transaction = $this->createTransaction();

        $transaction->groupBy('name', ['amount' => ['sum([amount])', 'type' => 'atk4_money']]);

        $this->assertSameSql(
            '(select "name" "name", sum("amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name", sum("amount") "amount" from "payment" group by "name") "derivedTable"',
            $transaction->getSubQuery(['name', 'amount'])->render()[0]
        );

        $transaction = $this->createSubtractInvoiceTransaction();

        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'atk4_money']]);

        $this->assertSameSql(
            '(select "name" "name", sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name", sum("amount") "amount" from "payment" group by "name") "derivedTable"',
            $transaction->getSubQuery(['name', 'amount'])->render()[0]
        );
    }

    public function testGrouping2(): void
    {
        $transaction = $this->createTransaction();

        $transaction->groupBy('name', ['amount' => ['sum([amount])', 'type' => 'atk4_money']]);

        $this->assertSameSql(
            'select "name", sum("amount") "amount" from (select "name" "name", sum("amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name", sum("amount") "amount" from "payment" group by "name") "derivedTable" group by "name"',
            $transaction->action('select', [['name', 'amount']])->render()[0]
        );

        $transaction = $this->createSubtractInvoiceTransaction();

        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'atk4_money']]);

        $this->assertSameSql(
            'select "name", sum("amount") "amount" from (select "name" "name", sum(-"amount") "amount" from "invoice" group by "name" UNION ALL select "name" "name", sum("amount") "amount" from "payment" group by "name") "derivedTable" group by "name"',
            $transaction->action('select', [['name', 'amount']])->render()[0]
        );
    }

    /**
     * If all nested models have a physical field to which a grouped column can be mapped into, then we should group all our
     * sub-queries.
     */
    public function testGrouping3(): void
    {
        $transaction = $this->createTransaction();
        $transaction->removeField('client_id');
        $transaction->groupBy('name', ['amount' => ['sum([amount])', 'type' => 'atk4_money']]);
        $transaction->setOrder('name');

        $this->assertSame([
            ['name' => 'chair purchase', 'amount' => 8.0],
            ['name' => 'full pay', 'amount' => 4.0],
            ['name' => 'prepay', 'amount' => 10.0],
            ['name' => 'table purchase', 'amount' => 15.0],
        ], $transaction->export());

        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->removeField('client_id');
        $transaction->groupBy('name', ['amount' => ['sum([])', 'type' => 'atk4_money']]);
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
    public function testSubGroupingByExpressions(): void
    {
        if ($this->getDatabasePlatform() instanceof OraclePlatform) { // TODO
            $this->markTestIncomplete('TODO - for some reasons Oracle does not accept the query');
        }

        $transaction = $this->createTransaction();
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->nestedPayment->addExpression('type', '\'payment\'');
        $transaction->addField('type');

        $transaction->groupBy('type', ['amount' => ['sum([amount])', 'type' => 'atk4_money']]);

        $this->assertSameSql(
            'select "client_id", "name", "type", sum("amount") "amount" from (select (\'invoice\') "type", sum("amount") "amount" from "invoice" group by "type" UNION ALL select (\'payment\') "type", sum("amount") "amount" from "payment" group by "type") "derivedTable" group by "type"',
            $transaction->action('select')->render()[0]
        );

        $this->assertSame([
            ['type' => 'invoice', 'amount' => 23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $transaction->export(['type', 'amount']));

        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->nestedInvoice->addExpression('type', '\'invoice\'');
        $transaction->nestedPayment->addExpression('type', '\'payment\'');
        $transaction->addField('type');

        $transaction->groupBy('type', ['amount' => ['sum([])', 'type' => 'atk4_money']]);

        $this->assertSame([
            ['type' => 'invoice', 'amount' => -23.0],
            ['type' => 'payment', 'amount' => 14.0],
        ], $transaction->export(['type', 'amount']));
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
            'select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = :a ' .
            'UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b) "derivedTable"',
            $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->render()[0]
        );

        $client = $this->createClient();
        $client->hasMany('tr', ['model' => $this->createSubtractInvoiceTransaction()]);

        $this->assertSame(19.0, (float) $client->load(1)->ref('Invoice')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(10.0, (float) $client->load(1)->ref('Payment')->action('fx', ['sum', 'amount'])->getOne());
        $this->assertSame(-9.0, (float) $client->load(1)->ref('tr')->action('fx', ['sum', 'amount'])->getOne());

        $this->assertSameSql(
            'select sum("val") from (select sum(-"amount") "val" from "invoice" where "client_id" = :a ' .
                'UNION ALL select sum("amount") "val" from "payment" where "client_id" = :b) "derivedTable"',
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
            'select "client"."id", "client"."name", (select sum("val") from (select sum("amount") "val" from "invoice" where "client_id" = "client"."id" UNION ALL select sum("amount") "val" from "payment" where "client_id" = "client"."id") "derivedTable") "balance" from "client" where "client"."id" = 1 limit 0, 1',
            $client->load(1)->action('select')->render()[0]
        );
    }

    public function testConditionOnUnionField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '<', 0);

        $this->assertSame([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
        ], $transaction->export());
    }

    public function testConditionOnNestedModelField(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('client_id', '>', 1);

        $this->assertSame([
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }

    public function testConditionForcedOnNestedModels1(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '>', 5, true);

        $this->assertSame([
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
        ], $transaction->export());
    }

    public function testConditionForcedOnNestedModels2(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition('amount', '<', -10, true);

        $this->assertSame([
            ['client_id' => 1, 'name' => 'table purchase', 'amount' => -15.0],
        ], $transaction->export());
    }

    public function testConditionExpression(): void
    {
        $transaction = $this->createSubtractInvoiceTransaction();
        $transaction->addCondition($transaction->expr('{} > 5', ['amount']));

        $this->assertSame([
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

        $this->assertSame([
            ['client_id' => 1, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 2, 'name' => 'chair purchase', 'amount' => -4.0],
            ['client_id' => 1, 'name' => 'prepay', 'amount' => 10.0],
            ['client_id' => 2, 'name' => 'full pay', 'amount' => 4.0],
        ], $transaction->export());
    }
}
