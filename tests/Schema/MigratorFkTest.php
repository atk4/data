<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\Platforms\MySQLPlatform;
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

        $res = array_map(function (Index $v) {
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

        $res = array_map(function (ForeignKeyConstraint $v) {
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

        $this->createMigrator($client)->createIndex([$client->getField('name')], false);
        self::assertSame([[['name'], false]], $this->listTableIndexes('client'));

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
        self::assertSame([], $this->listTableIndexes('client'));

        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $serverVersion = $this->getConnection()->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
            if (preg_match('~^5\.6~', $serverVersion)) {
                self::markTestIncomplete('TODO MySQL 5.6: Unique key exceed max key (767 bytes) length');
            }
        }

        $this->createMigrator($client)->createIndex([$client->getField('name')], true);
        self::assertSame([[['name'], true]], $this->listTableIndexes('client'));

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
}
