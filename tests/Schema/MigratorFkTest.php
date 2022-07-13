<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;

class MigratorFkTest extends TestCase
{
    /**
     * @param array<string> $localColumns
     * @param array<string> $targetColumns
     */
    protected function createForeignKey(string $localTable, array $localColumns, string $targetTable, array $targetColumns): void
    {
        $platform = $this->getDatabasePlatform();
        $this->getConnection()->createSchemaManager()->createForeignKey(
            new ForeignKeyConstraint(
                array_map(fn ($v) => $platform->quoteIdentifier($v), $localColumns),
                $platform->quoteIdentifier($targetTable),
                array_map(fn ($v) => $platform->quoteIdentifier($v), $targetColumns)
            ),
            $platform->quoteIdentifier($localTable)
        );
    }

    protected function selectTableForeignKeys(string $localTable): array
    {
        $foreignKeys = $this->getConnection()->createSchemaManager()->listTableForeignKeys($localTable);

        $unquoteIdentifierFx = fn (string $name): string => (new Identifier($name))->getName();

        $res = array_map(function (ForeignKeyConstraint $v) use ($unquoteIdentifierFx) {
            return [
                array_map($unquoteIdentifierFx, $v->getLocalColumns()),
                $unquoteIdentifierFx($v->getForeignTableName()),
                array_map($unquoteIdentifierFx, $v->getForeignColumns()),
            ];
        }, $foreignKeys);
        sort($res);

        return $res;
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

        $this->createForeignKey('client', ['country_id'], 'country', ['id']);
        $this->createForeignKey('client', ['created_by_client_id'], 'client', ['id']);
        $this->createForeignKey('invoice', ['client_id'], 'client', ['id']);

        // make sure FK client-country was not removed during FK invoice-client setup
        $this->assertSame([
            [],
            [[['country_id'], 'country', ['id']], [['created_by_client_id'], 'client', ['id']]],
            [[['client_id'], 'client', ['id']]],
        ], [
            $this->selectTableForeignKeys('country'),
            $this->selectTableForeignKeys('client'),
            $this->selectTableForeignKeys('invoice'),
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
            if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
                // FK violation exception is not properly converted by ExceptionConverter
                // https://github.com/doctrine/dbal/blob/3.3.7/src/Driver/API/SQLite/ExceptionConverter.php
                // https://github.com/doctrine/dbal/issues/5496
                // TODO submit a PR to DBAL
                $this->assertInstanceOf(DbalDriverException::class, $dbalException);
            } else {
                $this->assertInstanceOf(ForeignKeyConstraintViolationException::class, $dbalException);
            }

            throw $e;
        }
    }
}
