<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class JoinArrayTest extends TestCase
{
    private function getInternalPersistenceData(Persistence\Array_ $db): array
    {
        $data = [];
        /** @var Persistence\Array_\Db\Table $table */
        foreach ($this->getProtected($db, 'data') as $table) {
            foreach ($table->getRows() as $row) {
                $rowData = $row->getData();
                $id = $rowData['id'];
                unset($rowData['id']);
                $data[$table->getTableName()][$id] = $rowData;
            }
        }

        return $data;
    }

    public function testDirection(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m = new Model($db, ['table' => 'user']);

        $j = $m->join('contact');
        $this->assertFalse($this->getProtected($j, 'reverse'));
        $this->assertSame('contact_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact2.test_id');
        $this->assertTrue($this->getProtected($j, 'reverse'));
        $this->assertSame('id', $this->getProtected($j, 'master_field'));
        $this->assertSame('test_id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact3', ['master_field' => 'test_id']);
        $this->assertFalse($this->getProtected($j, 'reverse'));
        $this->assertSame('test_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('id', $this->getProtected($j, 'foreign_field'));

        $this->expectException(Exception::class); // TODO not implemented yet, see https://github.com/atk4/data/issues/803
        $j = $m->join('contact4.foo_id', ['master_field' => 'test_id', 'reverse' => true]);
        $this->assertTrue($this->getProtected($j, 'reverse'));
        $this->assertSame('test_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('foo_id', $this->getProtected($j, 'foreign_field'));
    }

    public function testJoinException(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m = new Model($db, ['table' => 'user']);

        $this->expectException(Exception::class);
        $j = $m->join('contact.foo_id', ['master_field' => 'test_id']);
    }

    public function testJoinSaving1(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('contact_id', ['type' => 'integer']);
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'John');
        $m_u2->set('contact_phone', '+123');
        $m_u2->save();

        $this->assertEquals([
            'user' => [1 => ['name' => 'John', 'contact_id' => 1]],
            'contact' => [1 => ['contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));

        $m_u2->unload();
        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'Peter');
        $m_u2->set('contact_id', 1);
        $m_u2->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
            ],
        ], $this->getInternalPersistenceData($db));

        $m_u2->unload();
        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'Joe');
        $m_u2->set('contact_phone', '+321');
        $m_u2->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
                2 => ['contact_phone' => '+321'],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testJoinSaving2(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('name');
        $j = $m_u->join('contact.test_id');
        $j->addField('contact_phone');
        $j->addField('test_id', ['type' => 'integer']);

        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'John');
        $m_u2->set('contact_phone', '+123');
        $m_u2->save();

        $this->assertEquals([
            'user' => [1 => ['name' => 'John']],
            'contact' => [1 => ['test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));

        $m_u2->unload();
        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'Peter');
        $m_u2->save();
        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John'],
                2 => ['name' => 'Peter'],
            ],
            'contact' => [
                1 => ['test_id' => 1, 'contact_phone' => '+123'],
                2 => ['test_id' => 2, 'contact_phone' => null],
            ],
        ], $this->getInternalPersistenceData($db));

        $m_c = new Model($db, ['table' => 'contact']);
        $m_c = $m_c->load(2);
        $m_c->delete();

        $m_u2->unload();
        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'Sue');
        $m_u2->set('contact_phone', '+444');
        $m_u2->save();
        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John'],
                2 => ['name' => 'Peter'],
                3 => ['name' => 'Sue'],
            ],
            'contact' => [
                1 => ['test_id' => 1, 'contact_phone' => '+123'],
                3 => ['test_id' => 3, 'contact_phone' => '+444'],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testJoinSaving3(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('name');
        $m_u->addField('test_id', ['type' => 'integer']);
        $j = $m_u->join('contact', ['master_field' => 'test_id']);
        $j->addField('contact_phone');
        $m_u = $m_u->createEntity();

        $m_u->set('name', 'John');
        $m_u->set('contact_phone', '+123');

        $m_u->save();

        $this->assertEquals([
            'user' => [1 => ['test_id' => 1, 'name' => 'John']],
            'contact' => [1 => ['contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));
    }

    /*public function testJoinSaving4(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('name');
        $m_u->addField('code');
        $j = $m_u->join('contact.code', ['master_field' => 'code']);
        $j->addField('contact_phone');
        $m_u = $m_u->createEntity();

        $m_u->set('name', 'John');
        $m_u->set('code', 'C28');
        $m_u->set('contact_phone', '+123');

        $m_u->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'code' => 'C28', 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'code' => 'C28', 'contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));
    }*/

    public function testJoinLoading(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
                2 => ['contact_phone' => '+321'],
            ],
        ]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('contact_id', ['type' => 'integer']);
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u2 = $m_u->load(1);
        $this->assertSame([
            'id' => 1, 'contact_id' => 1, 'name' => 'John', 'contact_phone' => '+123',
        ], $m_u2->get());

        $m_u2 = $m_u->load(3);
        $this->assertSame([
            'id' => 3, 'contact_id' => 2, 'name' => 'Joe', 'contact_phone' => '+321',
        ], $m_u2->get());

        $m_u2 = $m_u2->unload();
        $this->assertSame([
            'id' => null, 'contact_id' => null, 'name' => null, 'contact_phone' => null,
        ], $m_u2->get());

        $this->assertNull($m_u->tryLoad(4));
    }

    public function testJoinUpdate(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
                2 => ['contact_phone' => '+321'],
            ],
        ]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('contact_id', ['type' => 'integer']);
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u2 = $m_u->load(1);
        $m_u2->set('name', 'John 2');
        $m_u2->set('contact_phone', '+555');
        $m_u2->save();

        $this->assertSame([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                2 => ['contact_phone' => '+321'],
            ],
        ], $this->getInternalPersistenceData($db));

        $m_u2 = $m_u->load(3);
        $m_u2->set('name', 'XX');
        $m_u2->set('contact_phone', '+999');
        $m_u2->save();

        $this->assertSame([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'XX', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                2 => ['contact_phone' => '+999'],
            ],
        ], $this->getInternalPersistenceData($db));

        $m_u2 = $m_u->createEntity();
        $m_u2->set('name', 'YYY');
        $m_u2->set('contact_phone', '+777');
        $m_u2->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'XX', 'contact_id' => 2],
                4 => ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                2 => ['contact_phone' => '+999'],
                3 => ['contact_phone' => '+777'],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testJoinDelete(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'XX', 'contact_id' => 2],
                4 => ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                2 => ['contact_phone' => '+999'],
                3 => ['contact_phone' => '+777'],
            ],
        ]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('contact_id', ['type' => 'integer']);
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u = $m_u->load(1);
        $m_u->delete();

        $this->assertSame([
            'user' => [
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'XX', 'contact_id' => 2],
                4 => ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                2 => ['contact_phone' => '+999'],
                3 => ['contact_phone' => '+777'],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testLoadMissing(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                2 => ['name' => 'Peter', 'contact_id' => 1],
                3 => ['name' => 'XX', 'contact_id' => 2],
                4 => ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                2 => ['contact_phone' => '+999'],
                3 => ['contact_phone' => '+777'],
            ],
        ]);
        $m_u = new Model($db, ['table' => 'user']);
        $m_u->addField('contact_id', ['type' => 'integer']);
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');
        $this->expectException(Exception::class);
        $m_u = $m_u->load(2);
    }

    public function testForeignFieldNameGuessTableWithSchema(): void
    {
        $db = new Persistence\Array_();

        $m = new Model($db, ['table' => 'db.user']);
        $j = $m->join('contact');
        $this->assertFalse($this->getProtected($j, 'reverse'));
        $this->assertSame('contact_id', $this->getProtected($j, 'master_field'));
        $this->assertSame('id', $this->getProtected($j, 'foreign_field'));

        $j = $m->join('contact2', ['reverse' => true]);
        $this->assertTrue($this->getProtected($j, 'reverse'));
        $this->assertSame('id', $this->getProtected($j, 'master_field'));
        $this->assertSame('user_id', $this->getProtected($j, 'foreign_field'));
    }
}
