<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Schema;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Exception\ForeignKeyConstraintViolationException;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;

class MigratorFkTest extends TestCase
{
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
        $this->assertSame([
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
            $this->assertInstanceOf(ForeignKeyConstraintViolationException::class, $dbalException);

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
