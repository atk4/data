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
        // $this->assertTrue($this->getProtected($j, 'reverse'));
        // $this->assertSame('test_id', $this->getProtected($j, 'master_field'));
        // $this->assertSame('foo_id', $this->getProtected($j, 'foreign_field'));
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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');
        $user2->save();

        $this->assertEquals([
            'user' => [1 => ['name' => 'John', 'contact_id' => 1]],
            'contact' => [1 => ['contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Peter');
        $user2->set('contact_id', 1);
        $user2->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                2 => ['name' => 'Peter', 'contact_id' => 1],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
            ],
        ], $this->getInternalPersistenceData($db));

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Joe');
        $user2->set('contact_phone', '+321');
        $user2->save();

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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('contact.test_id');
        $j->addField('contact_phone');
        $j->addField('test_id', ['type' => 'integer']);

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');
        $user2->save();

        $this->assertEquals([
            'user' => [1 => ['name' => 'John']],
            'contact' => [1 => ['test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Peter');
        $user2->save();
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

        $contact = new Model($db, ['table' => 'contact']);
        $contact = $contact->load(2);
        $contact->delete();

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Sue');
        $user2->set('contact_phone', '+444');
        $user2->save();
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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('test_id', ['type' => 'integer']);
        $j = $user->join('contact', ['master_field' => 'test_id']);
        $j->addField('contact_phone');
        $user = $user->createEntity();

        $user->set('name', 'John');
        $user->set('contact_phone', '+123');

        $user->save();

        $this->assertEquals([
            'user' => [1 => ['test_id' => 1, 'name' => 'John']],
            'contact' => [1 => ['contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));
    }

    /* Joining tables on non-id fields is not implemented yet
    public function testJoinSaving4(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $user = new Model($db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('code');
        $j = $user->join('contact.code', ['master_field' => 'code']);
        $j->addField('contact_phone');
        $user = $user->createEntity();

        $user->set('name', 'John');
        $user->set('code', 'C28');
        $user->set('contact_phone', '+123');

        $user->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'code' => 'C28', 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'code' => 'C28', 'contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));
    }
    */

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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        $this->assertSame([
            'id' => 1, 'contact_id' => 1, 'name' => 'John', 'contact_phone' => '+123',
        ], $user2->get());

        $user2 = $user->load(3);
        $this->assertSame([
            'id' => 3, 'contact_id' => 2, 'name' => 'Joe', 'contact_phone' => '+321',
        ], $user2->get());

        $user2 = $user2->unload();
        $this->assertSame([
            'id' => null, 'contact_id' => null, 'name' => null, 'contact_phone' => null,
        ], $user2->get());

        $this->assertNull($user->tryLoad(4));
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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        $user2->set('name', 'John 2');
        $user2->set('contact_phone', '+555');
        $user2->save();

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

        $user2 = $user->load(3);
        $user2->set('name', 'XX');
        $user2->set('contact_phone', '+999');
        $user2->save();

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

        $user2 = $user->createEntity();
        $user2->set('name', 'YYY');
        $user2->set('contact_phone', '+777');
        $user2->save();

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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user = $user->load(1);
        $user->delete();

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
        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $this->expectException(Exception::class);
        $user = $user->load(2);
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
