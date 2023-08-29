<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class JoinArrayTest extends TestCase
{
    /**
     * @return array<string, array<mixed, mixed>>
     */
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
        self::assertFalse($j->reverse);
        self::assertSame('contact_id', $this->getProtected($j, 'masterField'));
        self::assertSame('id', $this->getProtected($j, 'foreignField'));

        $j = $m->join('contact2.test_id');
        self::assertTrue($j->reverse);
        self::assertSame('id', $this->getProtected($j, 'masterField'));
        self::assertSame('test_id', $this->getProtected($j, 'foreignField'));

        $j = $m->join('contact3', ['masterField' => 'test_id']);
        self::assertFalse($j->reverse);
        self::assertSame('test_id', $this->getProtected($j, 'masterField'));
        self::assertSame('id', $this->getProtected($j, 'foreignField'));

        $this->expectException(Exception::class); // TODO not implemented yet, see https://github.com/atk4/data/issues/803
        $j = $m->join('contact4.foo_id', ['masterField' => 'test_id', 'reverse' => true]);
        // self::assertTrue($j->reverse);
        // self::assertSame('test_id', $this->getProtected($j, 'masterField'));
        // self::assertSame('foo_id', $this->getProtected($j, 'foreignField'));
    }

    public function testJoinException(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $m = new Model($db, ['table' => 'user']);

        $this->expectException(Exception::class);
        $m->join('contact.foo_id', ['masterField' => 'test_id']);
    }

    public function testJoinSaving1(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $user = new Model($db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('contact_id', ['type' => 'integer']);
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');
        $j->allowDangerousForeignTableUpdate = true;
        $user2->save();

        self::assertSame(1, $user2->getId());
        self::assertSame('John', $user2->get('name'));
        self::assertSame('+123', $user2->get('contact_phone'));

        self::assertSame([
            'user' => [1 => ['name' => 'John', 'contact_id' => 1]],
            'contact' => [1 => ['contact_phone' => '+123']],
        ], $this->getInternalPersistenceData($db));

        $user2 = $user->createEntity();
        $user2->set('name', 'Peter');
        $user2->set('contact_id', 1);
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
            ],
        ], $this->getInternalPersistenceData($db));

        $user2 = $user->createEntity();
        $user2->set('name', 'Joe');
        $user2->set('contact_phone', '+321');
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
                ['contact_phone' => '+321'],
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
        $j->allowDangerousForeignTableUpdate = true;
        $user2->save();

        self::assertSame(1, $user2->getId());
        self::assertSame('John', $user2->get('name'));
        self::assertSame('+123', $user2->get('contact_phone'));

        self::assertSame([
            'user' => [1 => ['name' => 'John']],
            'contact' => [1 => ['contact_phone' => '+123', 'test_id' => 1]],
        ], $this->getInternalPersistenceData($db));

        $user2 = $user->createEntity();
        $user2->set('name', 'Peter');
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John'],
                ['name' => 'Peter'],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123', 'test_id' => 1],
                ['contact_phone' => null, 'test_id' => 2],
            ],
        ], $this->getInternalPersistenceData($db));

        $contact = new Model($db, ['table' => 'contact']);
        $contact = $contact->load(2);
        $contact->delete();

        $user2 = $user->createEntity();
        $user2->set('name', 'Sue');
        $user2->set('contact_phone', '+444');
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John'],
                ['name' => 'Peter'],
                ['name' => 'Sue'],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123', 'test_id' => 1],
                3 => ['contact_phone' => '+444', 'test_id' => 3],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testJoinSaving3(): void
    {
        $db = new Persistence\Array_(['user' => [], 'contact' => []]);
        $user = new Model($db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('test_id', ['type' => 'integer']);
        $j = $user->join('contact', ['masterField' => 'test_id']);
        $j->addField('contact_phone');

        $user = $user->createEntity();
        $user->set('name', 'John');
        $user->set('contact_phone', '+123');
        $j->allowDangerousForeignTableUpdate = true;
        $user->save();

        self::assertSame([
            'user' => [1 => ['name' => 'John', 'test_id' => 1]],
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
        $j = $user->join('contact.code', ['masterField' => 'code']);
        $j->addField('contact_phone');

        $user = $user->createEntity();
        $user->set('name', 'John');
        $user->set('code', 'C28');
        $user->set('contact_phone', '+123');
        $j->allowDangerousForeignTableUpdate = true;
        $user->save();

        self::assertSame([
            'user' => [
                1 => ['id' => 1, 'code' => 'C28', 'name' => 'John'],
            ],
            'contact' => [
                1 => ['id' => 1, 'code' => 'C28', 'contact_phone' => '+123'],
            ],
        ], $this->getInternalPersistenceData($db));
    }
    */

    public function testJoinLoading(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
                ['contact_phone' => '+321'],
            ],
        ]);

        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        self::assertSame([
            'id' => 1, 'contact_id' => 1, 'name' => 'John', 'contact_phone' => '+123',
        ], $user2->get());

        $user2 = $user->load(3);
        self::assertSame([
            'id' => 3, 'contact_id' => 2, 'name' => 'Joe', 'contact_phone' => '+321',
        ], $user2->get());

        $user2->unload();
        self::assertSame([
            'id' => null, 'contact_id' => null, 'name' => null, 'contact_phone' => null,
        ], $user2->get());

        self::assertNull($user->tryLoad(4));
    }

    public function testJoinUpdate(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+123'],
                ['contact_phone' => '+321'],
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
        $j->allowDangerousForeignTableUpdate = true;
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                ['contact_phone' => '+321'],
            ],
        ], $this->getInternalPersistenceData($db));

        $user2 = $user->load(3);
        $user2->set('name', 'XX');
        $user2->set('contact_phone', '+999');
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'XX', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                ['contact_phone' => '+999'],
            ],
        ], $this->getInternalPersistenceData($db));

        $user2 = $user->createEntity();
        $user2->set('name', 'YYY');
        $user2->set('contact_phone', '+777');
        $user2->save();

        self::assertSame([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'XX', 'contact_id' => 2],
                ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                ['contact_phone' => '+999'],
                ['contact_phone' => '+777'],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testJoinDelete(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                1 => ['name' => 'John 2', 'contact_id' => 1],
                ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'XX', 'contact_id' => 2],
                ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['contact_phone' => '+555'],
                ['contact_phone' => '+999'],
                ['contact_phone' => '+777'],
            ],
        ]);

        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user = $user->load(1);
        $j->allowDangerousForeignTableUpdate = true;
        $user->delete();

        self::assertSame([
            'user' => [
                2 => ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'XX', 'contact_id' => 2],
                ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                2 => ['contact_phone' => '+999'],
                ['contact_phone' => '+777'],
            ],
        ], $this->getInternalPersistenceData($db));
    }

    public function testLoadMissingException(): void
    {
        $db = new Persistence\Array_([
            'user' => [
                2 => ['name' => 'Peter', 'contact_id' => 1],
                ['name' => 'XX', 'contact_id' => 2],
                ['name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                2 => ['contact_phone' => '+999'],
                ['contact_phone' => '+777'],
            ],
        ]);

        $user = new Model($db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Unable to load joined record');
        $user->load(2);
    }

    public function testForeignFieldNameGuessTableWithSchema(): void
    {
        $db = new Persistence\Array_();

        $m = new Model($db, ['table' => 'db.user']);
        $j = $m->join('contact');
        self::assertFalse($j->reverse);
        self::assertSame('contact_id', $this->getProtected($j, 'masterField'));
        self::assertSame('id', $this->getProtected($j, 'foreignField'));

        $j = $m->join('contact2', ['reverse' => true]);
        self::assertTrue($j->reverse);
        self::assertSame('id', $this->getProtected($j, 'masterField'));
        self::assertSame('user_id', $this->getProtected($j, 'foreignField'));
    }
}
