<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model\AggregateModel;
use Atk4\Data\Model\Scope;
use Atk4\Data\Model\Scope\Condition;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class ModelAggregateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->setDb([
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
        ]);
    }

    /**
     * TODO when aggregating on ID field, we can assume uniqueness, and any other model field
     * should NOT be needed to be added to GROUP BY explicitly.
     */
    private static function fixAllNonAggregatedFieldsInGroupBy(AggregateModel $model): void
    {
        $model->setGroupBy(['client']);
    }

    protected function createInvoice(): Model\Invoice
    {
        $invoice = new Model\Invoice($this->db);
        $invoice->getReference('client_id')->addTitle();

        return $invoice;
    }

    protected function createInvoiceAggregate(): AggregateModel
    {
        return new AggregateModel($this->createInvoice());
    }

    public function testGroupBy(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->setGroupBy(['client_id'], [
            'c' => ['expr' => 'count(*)', 'type' => 'integer'],
        ]);

        $this->assertSameExportUnordered([
            ['client_id' => 1, 'c' => 2],
            ['client_id' => 2, 'c' => 1],
        ], $aggregate->export());
    }

    public function testGroupSelect(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'c' => ['expr' => 'count(*)', 'type' => 'integer'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $this->assertSameExportUnordered([
            ['client' => 'Vinny', 'client_id' => 1, 'c' => 2],
            ['client' => 'Zoe', 'client_id' => 2, 'c' => 1],
        ], $aggregate->export());
    }

    public function testGroupSelect2(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $this->assertSameExportUnordered([
            ['client' => 'Vinny', 'client_id' => 1, 'amount' => 19.0],
            ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
        ], $aggregate->export());
    }

    public function testGroupSelect3(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'min' => ['expr' => 'min([amount])', 'type' => 'atk4_money'],
            'max' => ['expr' => 'max([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'], // same as `s`, but reuse name `amount`
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $this->assertSameExportUnordered([
            ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'min' => 4.0, 'max' => 15.0, 'amount' => 19.0],
            ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'min' => 4.0, 'max' => 4.0, 'amount' => 4.0],
        ], $aggregate->export());
    }

    public function testGroupSelectExpr(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->addExpression('double', ['expr' => '[s] + [amount]', 'type' => 'atk4_money']);

        $this->assertSameExportUnordered([
            ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
            ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
        ], $aggregate->export());
    }

    public function testGroupSelectCondition(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');
        $aggregate->table->addCondition('name', 'chair purchase');

        $aggregate->setGroupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->addExpression('double', ['expr' => '[s] + [amount]', 'type' => 'atk4_money']);

        $this->assertSameExportUnordered([
            ['client' => 'Vinny', 'client_id' => 1, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
            ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
        ], $aggregate->export());
    }

    public function testGroupSelectCondition2(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->addExpression('double', ['expr' => '[s] + [amount]', 'type' => 'atk4_money']);
        $aggregate->addCondition(
            'double',
            '>',
            // TODO Sqlite bind param does not work, expr needed, even if casted to float with DBAL type (comparison works only if casted to/bind as int)
            $this->getDatabasePlatform() instanceof SqlitePlatform ? $aggregate->expr('10') : 10
        );

        $this->assertSame([
            ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
        ], $aggregate->export());
    }

    public function testGroupSelectCondition3(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->addExpression('double', ['expr' => '[s] + [amount]', 'type' => 'atk4_money']);
        $aggregate->addCondition(
            'double',
            // TODO Sqlite bind param does not work, expr needed, even if casted to float with DBAL type (comparison works only if casted to/bind as int)
            $this->getDatabasePlatform() instanceof SqlitePlatform ? $aggregate->expr('38') : 38
        );

        $this->assertSame([
            ['client' => 'Vinny', 'client_id' => 1, 's' => 19.0, 'amount' => 19.0, 'double' => 38.0],
        ], $aggregate->export());
    }

    public function testGroupSelectCondition4(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            's' => ['expr' => 'sum([amount])', 'type' => 'atk4_money'],
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->addExpression('double', ['expr' => '[s] + [amount]', 'type' => 'atk4_money']);
        $aggregate->addCondition('client_id', 2);

        $this->assertSame([
            ['client' => 'Zoe', 'client_id' => 2, 's' => 4.0, 'amount' => 4.0, 'double' => 8.0],
        ], $aggregate->export());
    }

    public function testGroupSelectScope(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        // TODO Sqlite bind param does not work, expr needed, even if casted to float with DBAL type (comparison works only if casted to/bind as int)
        $numExpr = $this->getDatabasePlatform() instanceof SqlitePlatform ? $aggregate->expr('4') : 4;
        $scope = Scope::createAnd(new Condition('client_id', 2), new Condition('amount', $numExpr));
        $aggregate->addCondition($scope);

        $this->assertSame([
            ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
        ], $aggregate->export());
    }

    public function testGroupSelectRef(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'c' => ['expr' => 'count(*)', 'type' => 'integer'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->hasOne('client_id', ['model' => [Model\Invoice::class]]);

        $this->assertSame(1, $aggregate->loadBy('client', 'Vinny')->ref('client_id')->id);
        $this->assertSame(2, $aggregate->loadBy('client', 'Zoe')->ref('client_id')->id);
        $aggregate->table->addCondition('client', 'Zoe');
        $this->assertSame(2, $aggregate->ref('client_id')->loadOne()->id);
    }

    public function testGroupOrderSql(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $aggregate->setOrder('client_id', 'asc');

        $this->assertSameSql(
            'select "client", "client_id", sum("amount") "amount" from (select "id", "client_id", "name", "amount", (select "name" from "client" "_c_2bfe9d72a4aa" where "id" = "invoice"."client_id") "client" from "invoice") "_tm" group by "client_id", "client" order by "client_id"',
            $aggregate->action('select')->render()[0]
        );

        // TODO subselect should not select "client" field
        $aggregate->removeField('client');
        $aggregate->groupByFields = array_diff($aggregate->groupByFields, ['client']);
        $this->assertSameSql(
            'select "client_id", sum("amount") "amount" from (select "id", "client_id", "name", "amount", (select "name" from "client" "_c_2bfe9d72a4aa" where "id" = "invoice"."client_id") "client" from "invoice") "_tm" group by "client_id" order by "client_id"',
            $aggregate->action('select')->render()[0]
        );
    }

    public function testGroupLimit(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);
        $aggregate->setLimit(1);
        $aggregate->setOrder('client_id', 'asc');

        $this->assertSame([
            ['client' => 'Vinny', 'client_id' => 1, 'amount' => 19.0],
        ], $aggregate->export());
    }

    public function testGroupLimit2(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);
        $aggregate->setLimit(2, 1);
        $aggregate->setOrder('client_id', 'asc');

        $this->assertSame([
            ['client' => 'Zoe', 'client_id' => 2, 'amount' => 4.0],
        ], $aggregate->export());
    }

    public function testGroupCountSql(): void
    {
        $aggregate = $this->createInvoiceAggregate();
        $aggregate->addField('client');

        $aggregate->setGroupBy(['client_id'], [
            'amount' => ['expr' => 'sum([])', 'type' => 'atk4_money'],
        ]);
        self::fixAllNonAggregatedFieldsInGroupBy($aggregate);

        $this->assertSameSql(
            'select count(*) from ((select 1 from (select "id", "client_id", "name", "amount", (select "name" from "client" "_c_2bfe9d72a4aa" where "id" = "invoice"."client_id") "client" from "invoice") "_tm" group by "client_id", "client")) "_tc"',
            $aggregate->action('count')->render()[0]
        );
    }

    public function testAggregateFieldExpressionSql(): void
    {
        $aggregate = $this->createInvoiceAggregate();

        $aggregate->setGroupBy([$aggregate->expr('{}', ['abc'])], [
            'xyz' => ['expr' => 'sum([amount])'],
        ]);

        $this->assertSameSql(
            'select sum("amount") "xyz" from (select "id", "client_id", "name", "amount", (select "name" from "client" "_c_2bfe9d72a4aa" where "id" = "invoice"."client_id") "client" from "invoice") "_tm" group by "abc"',
            $aggregate->action('select')->render()[0]
        );
    }
}
