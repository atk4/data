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
        $foreignKeys = $this->getConnection()->createSchemaManager()->listTableForeignKeys(
            $this->getDatabasePlatform()->quoteIdentifier($localTable)
        );

        return array_map(function (ForeignKeyConstraint $v) {
            return [$v->getLocalColumns(), $v->getForeignTableName(), $v->getForeignColumns()];
        }, $foreignKeys);
    }

    public function testForeignKeyDbalException(): void
    {
        $country = new Model($this->db, ['table' => 'country']);
        $country->addField('name');

        $client = new Model($this->db, ['table' => 'client']);
        $client->addField('name');
        $client->hasOne('country_id', ['model' => $country]);

        $invoice = new Model($this->db, ['table' => 'invoice']);
        $invoice->hasOne('client_id', ['model' => $client]);

        $this->createMigrator($client)->create();
        $this->createMigrator($invoice)->create();
        $this->createMigrator($country)->create();

        $this->createForeignKey('client', ['country_id'], 'country', ['id']);
        $this->createForeignKey('invoice', ['client_id'], 'client', ['id']);

        // make sure FK client-country was not removed during FK invoice-client setup
        $this->assertSame([], $this->selectTableForeignKeys('country'));
        $this->assertSame([
            [['country_id'], 'country', ['id']],
        ], $this->selectTableForeignKeys('client'));
        $this->assertSame([
            [['client_id'], 'client', ['id']],
        ], $this->selectTableForeignKeys('invoice'));

        $clientId = $client->insert(['name' => 'Leos']);
        $invoice->insert(['client_id' => $clientId]);

        $this->expectException(Exception::class);
        try {
            $invoice->insert(['client_id' => 50]);
        } catch (Exception $e) {
            $dbalException = $e->getPrevious()->getPrevious();
            if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
                // FK violation exception is not properly converted by ExceptionConverter
                // https://github.com/doctrine/dbal/blob/3.3.7/src/Driver/API/SQLite/ExceptionConverter.php
                // TODO submit a PR to DBAL
                $this->assertInstanceOf(DbalDriverException::class, $dbalException);
            } else {
                $this->assertInstanceOf(ForeignKeyConstraintViolationException::class, $dbalException);
            }

            throw $e;
        }
    }
}
