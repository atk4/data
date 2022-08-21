<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class ReadOnlyModeTest extends TestCase
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

        $this->m = new Model($this->db, ['table' => 'user', 'readOnly' => true]);
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
        $this->assertSame('John', $m->get('name'));

        $this->m->order = [];
        $this->m->setOrder('name', 'desc');
        $m = $this->m->loadAny();
        $this->assertSame('Sue', $m->get('name'));

        $this->assertSame([2 => 'Sue', 1 => 'John'], $this->m->getTitles());
    }

    public function testLoad(): void
    {
        $m = $this->m->load(1);
        $this->assertTrue($m->isLoaded());
    }

    public function testInsert(): void
    {
        $this->expectException(Exception::class);
        $this->m->insert(['name' => 'Joe']);
    }

    public function testSave(): void
    {
        $m = $this->m->load(1);
        $m->set('name', 'X');

        $this->expectException(Exception::class);
        $m->save();
    }

    public function testSaveAndUnload(): void
    {
        $m = $this->m->loadAny();

        $this->expectException(Exception::class);
        $m->saveAndUnload();
    }

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
        $this->m->delete(1);
    }
}
