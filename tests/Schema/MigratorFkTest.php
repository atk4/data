<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\SQLServerPlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Index;

class MigratorFkTest extends TestCase
{
    /**
     * @return list<array{list<string>, bool}>
     */
    protected function listTableIndexes(string $localTable): array
    {
        $indexes = $this->getConnection()->createSchemaManager()->listTableIndexes($localTable);

        self::assertArrayHasKey('primary', $indexes);
        unset($indexes['primary']);

        $res = array_map(static function (Index $v) {
            self::assertFalse($v->isPrimary());

            return [
                $v->getUnquotedColumns(),
                $v->isUnique(),
            ];
        }, $indexes);
        sort($res);

        return $res;
    }

    /**
     * @return list<array{list<string>, string, list<string>}>
     */
    protected function listTableForeignKeys(string $localTable): array
    {
        $foreignKeys = $this->getConnection()->createSchemaManager()->listTableForeignKeys($localTable);

        $res = array_map(static function (ForeignKeyConstraint $v) {
            return [
                $v->getUnquotedLocalColumns(),
                $v->getForeignTableName(),
                $v->getUnquotedForeignColumns(),
            ];
        }, $foreignKeys);
        sort($res);

        return $res;
    }

    public function testCreateIndexNonUnique(): void
    {
        $client = new Model($this->db, ['table' => 'client']);
        $client->addField('name');

        $this->createMigrator($client)->create();
        self::assertSame([], $this->listTableIndexes('client'));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('id')], false));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('id')], true));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('name')], false));

        $this->createMigrator()->createIndex([$client->getField('name')], false);
        self::assertSame([[['name'], false]], $this->listTableIndexes('client'));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('name')], false));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('name')], true));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('id'), $client->getField('name')], false));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('name'), $client->getField('id')], false));

        $client->insert(['name' => 'Michael']);
        $client->insert(['name' => 'Denise']);
        $client->insert(['name' => null]);
        $client->insert(['name' => 'Michael']);
        $client->insert(['name' => null]);

        self::assertSameExportUnordered([
            ['id' => 1, 'name' => 'Michael'],
            ['id' => 2, 'name' => 'Denise'],
            ['id' => 3, 'name' => null],
            ['id' => 4, 'name' => 'Michael'],
            ['id' => 5, 'name' => null],
        ], $client->export());
    }

    public function testCreateIndexUnique(): void
    {
        $client = new Model($this->db, ['table' => 'client']);
        $client->addField('name');

        $this->createMigrator($client)->create();

        $this->markTestIncompleteOnMySQL56PlatformAsCreateUniqueStringIndexHasLengthLimit();

        $this->createMigrator()->createIndex([$client->getField('name')], true);
        self::assertSame([[['name'], true]], $this->listTableIndexes('client'));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('name')], false));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('name')], true));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('id'), $client->getField('name')], false));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('name'), $client->getField('id')], false));

        $client->insert(['name' => 'Michael']);
        $client->insert(['name' => 'Denise']);
        $client->insert(['name' => null]);
        $client->insert(['name' => null]);

        self::assertSameExportUnordered([
            ['id' => 1, 'name' => 'Michael'],
            ['id' => 2, 'name' => 'Denise'],
            ['id' => 3, 'name' => null],
            ['id' => 4, 'name' => null],
        ], $client->export());

        $this->expectException(Exception::class);
        try {
            $client->insert(['name' => 'Michael']);
        } catch (Exception $e) {
            $dbalException = $e->getPrevious()->getPrevious();
            self::assertInstanceOf(UniqueConstraintViolationException::class, $dbalException);

            throw $e;
        }
    }

    public function testCreateIndexMultipleFields(): void
    {
        $client = new Model($this->db, ['table' => 'client']);
        $client->addField('a');
        $client->addField('b');

        $this->createMigrator($client)->create();
        self::assertSame([], $this->listTableIndexes('client'));

        $this->markTestIncompleteOnMySQL56PlatformAsCreateUniqueStringIndexHasLengthLimit();

        $this->createMigrator($client)->createIndex([$client->getField('a'), $client->getField('b')], true);
        self::assertSame([[['a', 'b'], true]], $this->listTableIndexes('client'));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('a'), $client->getField('b')], false));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('a'), $client->getField('b')], true));
        self::assertTrue($this->createMigrator()->isIndexExists([$client->getField('a')], false));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('a')], true));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('b')], false));
        self::assertFalse($this->createMigrator()->isIndexExists([$client->getField('b'), $client->getField('a')], false));
    }

    public function testForeignKeyViolation(): void
    {
        $country = new Model($this->db, ['table' => 'country']);
        $country->addField('name');

        $client = new Model($this->db, ['table' => 'client']);
        $client->addField('name');
        $client->hasOne('country_id', ['model' => $country]);
        $client->hasOne('created_by_client_id', ['model' => $client]);

        $invoice = new Model($this->db, ['table' => 'invoice']);
        $invoice->hasOne('client_id', ['model' => $client]);

        $this->createMigrator($client)->create();
        $this->createMigrator($invoice)->create();
        $this->createMigrator($country)->create();

        $this->createMigrator()->createForeignKey($client->getReference('country_id'));
        $this->createMigrator()->createForeignKey($client->getReference('created_by_client_id'));
        $this->createMigrator()->createForeignKey($invoice->getReference('client_id'));

        // make sure FK client-country was not removed during FK invoice-client setup
        self::assertSame([
            [],
            [[['country_id'], 'country', ['id']], [['created_by_client_id'], 'client', ['id']]],
            [[['client_id'], 'client', ['id']]],
        ], [
            $this->listTableForeignKeys('country'),
            $this->listTableForeignKeys('client'),
            $this->listTableForeignKeys('invoice'),
        ]);

        $clientId = $client->insert(['name' => 'Leos']);
        $invoice->insert(['client_id' => $clientId]);

        // same table FK
        $client->insert(['name' => 'Ewa', 'created_by_client_id' => $clientId]);

        $this->expectException(Exception::class);
        try {
            $invoice->insert(['client_id' => 50]);
        } catch (Exception $e) {
            $dbalException = $e->getPrevious()->getPrevious();
            self::assertInstanceOf(ForeignKeyConstraintViolationException::class, $dbalException);

            throw $e;
        }
    }

    public function testForeignKeyViolationDuringSetup(): void
    {
        $country = new Model($this->db, ['table' => 'country']);
        $country->addField('name');

        $client = new Model($this->db, ['table' => 'client']);
        $client->addField('name');
        $client->hasOne('country_id', ['model' => $country]);

        $this->createMigrator($client)->create();
        $this->createMigrator($country)->create();

        $client->insert(['name' => 'Leos', 'country_id' => 10]);

        $this->expectException(DbalException::class);
        $this->createMigrator()->createForeignKey($client->getReference('country_id'));
    }

    public function testForeignKeyViolationWithoutPk(): void
    {
        $currency = new Model($this->db, ['table' => 'currency']);
        $currency->addField('code');
        $currency->addField('name');

        $price = new Model($this->db, ['table' => 'price']);
        $price->addField('amount', ['type' => 'float']);
        $price->addField('currency');

        $this->createMigrator($currency)->create();
        $this->createMigrator($price)->create();

        $currency->insert(['code' => 'EUR', 'name' => 'Euro']);
        $currency->insert(['code' => 'USD', 'name' => 'United States dollar']);
        $currency->insert(['code' => 'CZK', 'name' => 'Česká koruna']);

        $this->markTestIncompleteOnMySQL56PlatformAsCreateUniqueStringIndexHasLengthLimit();

        $this->createMigrator()->createForeignKey([$price->getField('currency'), $currency->getField('code')]);

        $price->insert(['amount' => 0.5, 'currency' => 'EUR']);
        $price->insert(['amount' => 1, 'currency' => 'EUR']);
        $price->insert(['amount' => 2, 'currency' => 'USD']);
        $price->insert(['amount' => 3, 'currency' => null]);
        $price->insert(['amount' => 4, 'currency' => null]);

        self::assertSameExportUnordered([
            ['id' => 1, 'amount' => 0.5, 'currency' => 'EUR'],
            ['id' => 2, 'amount' => 1.0, 'currency' => 'EUR'],
            ['id' => 3, 'amount' => 2.0, 'currency' => 'USD'],
            ['id' => 4, 'amount' => 3.0, 'currency' => null],
            ['id' => 5, 'amount' => 4.0, 'currency' => null],
        ], $price->export());

        $currency->insert(['code' => null, 'name' => 'Reward A']);
        if ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            // MSSQL unique index does not allow duplicate NULL values, if the index is created
            // with "WHERE xxx IS NOT NULL" then FK cannot be created
            // https://github.com/doctrine/dbal/issues/5507
        } else {
            $currency->insert(['code' => null, 'name' => 'Reward B']);
        }

        $this->expectException(Exception::class);
        try {
            $price->insert(['amount' => 5, 'currency' => 'JPY']);
        } catch (Exception $e) {
            $dbalException = $e->getPrevious()->getPrevious();
            self::assertInstanceOf(ForeignKeyConstraintViolationException::class, $dbalException);

            throw $e;
        }
    }
}
