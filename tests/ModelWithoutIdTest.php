<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

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
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $this->m = new Model($this->db, ['table' => 'user', 'idField' => false]);
        $this->m->addField('name');
        $this->m->addField('gender');
    }

    /**
     * Basic operation should work just fine on model without ID.
     */
    public function testBasic(): void
    {
        $this->m->setOrder('name', 'asc');
        $m = $this->m->loadAny();
        self::assertSame('John', $m->get('name'));

        $this->m->order = [];
        $this->m->setOrder('name', 'desc');
        $m = $this->m->loadAny();
        self::assertSame('Sue', $m->get('name'));

        $names = [];
        foreach ($this->m as $row) {
            $names[] = $row->get('name');
        }
        self::assertSame(['Sue', 'John'], $names);
    }

    public function testGetIdException(): void
    {
        $m = $this->m->loadAny();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ID field is not defined');
        $m->getId();
    }

    public function testSetIdException(): void
    {
        $m = $this->m->createEntity();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('ID field is not defined');
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
            self::markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $this->m->insert(['name' => 'Joe']);
        self::assertSame(3, $this->m->executeCountQuery());
    }

    /**
     * Since no ID is set, a new record will be created if saving is attempted.
     */
    public function testSave1(): void
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $m = $this->m->loadAny();
        $m->saveAndUnload();

        self::assertSame(3, $this->m->executeCountQuery());
    }

    /**
     * Calling save will always create new record.
     */
    public function testSave2(): void
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::markTestIncomplete('PostgreSQL requires PK specified in SQL to use autoincrement');
        }

        $m = $this->m->loadAny();
        $m->save();

        self::assertSame(3, $this->m->executeCountQuery());
    }

    public function testLoadBy(): void
    {
        $m = $this->m->loadBy('name', 'Sue');
        self::assertSame('Sue', $m->get('name'));
    }

    public function testLoadCondition(): void
    {
        $this->m->addCondition('name', 'Sue');
        $m = $this->m->loadAny();
        self::assertSame('Sue', $m->get('name'));
    }

    public function testFailDelete1(): void
    {
        $this->expectException(Exception::class);
        $this->m->delete(4);
    }
}
