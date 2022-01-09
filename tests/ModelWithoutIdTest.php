<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

/**
 * Tests cases when model have to work with data that does not have ID field.
 */
class ModelWithoutIdTest extends TestCase
{
    /** @var Model */
    public $m;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $this->m = new Model($this->db, ['table' => 'user', 'id_field' => false]);

        $this->m->addFields(['name', 'gender']);
    }

    /**
     * Basic operation should work just fine on model without ID.
     */
    public function testBasic(): void
    {
        $this->m->setOrder('name', 'asc');
        $m = $this->m->tryLoadAny();
        $this->assertSame('John', $m->get('name'));

        $this->m->setOrder('name', 'desc');
        $m = $this->m->tryLoadAny();
        $this->assertSame('Sue', $m->get('name'));

        $names = [];
        foreach ($this->m as $row) {
            $names[] = $row->get('name');
        }
        $this->assertSame(['Sue', 'John'], $names);
    }

    public function testGetIdException(): void
    {
        $m = $this->m->loadAny();
        $this->expectException(Exception::class);
        $this->expectErrorMessage('ID field is not defined');
        $m->getId();
    }

    public function testSetIdException(): void
    {
        $m = $this->m->createEntity();
        $this->expectException(Exception::class);
        $this->expectErrorMessage('ID field is not defined');
        $m->setId(1);
    }

    public function testFail1(): void
    {
        $this->expectException(Exception::class);
        $this->m->load(1);
    }

    /**
     * Inserting into model without ID should be OK.
     */
    public function testInsert(): void
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $this->m->insert(['name' => 'Joe']);
        $this->assertSame('3', $this->m->action('count')->getOne());
    }

    /**
     * Since no ID is set, a new record will be created if saving is attempted.
     */
    public function testSave1(): void
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $m = $this->m->tryLoadAny();
        $m->saveAndUnload();

        $this->assertSame('3', $this->m->action('count')->getOne());
    }

    /**
     * Calling save will always create new record.
     */
    public function testSave2(): void
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $this->markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $m = $this->m->tryLoadAny();
        $m->save();

        $this->assertSame('3', $this->m->action('count')->getOne());
    }

    /**
     * Conditions should work fine.
     */
    public function testLoadBy(): void
    {
        $m = $this->m->loadBy('name', 'Sue');
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testLoadCondition(): void
    {
        $this->m->addCondition('name', 'Sue');
        $m = $this->m->loadAny();
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testFailDelete1(): void
    {
        $this->expectException(Exception::class);
        $this->m->delete(4);
    }
}
