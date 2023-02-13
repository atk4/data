<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;

class JoinSqlTest extends TestCase
{
    public function testDirection(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $j1 = $m->join('contact');
        static::assertFalse($this->getProtected($j1, 'reverse'));
        static::assertSame('contact_id', $this->getProtected($j1, 'masterField'));
        static::assertSame('id', $this->getProtected($j1, 'foreignField'));

        $j2 = $m->join('contact2.test_id');
        static::assertTrue($this->getProtected($j2, 'reverse'));
        static::assertSame('id', $this->getProtected($j2, 'masterField'));
        static::assertSame('test_id', $this->getProtected($j2, 'foreignField'));

        $j3 = $m->join('contact3', ['masterField' => 'test_id']);
        static::assertFalse($this->getProtected($j3, 'reverse'));
        static::assertSame('test_id', $this->getProtected($j3, 'masterField'));
        static::assertSame('id', $this->getProtected($j3, 'foreignField'));

        static::assertSame([
            'contact' => $j1,
            'contact2' => $j2,
            'contact3' => $j3,
        ], $m->getJoins());
        static::assertSame($j1, $m->getJoin('contact'));
        static::assertSame($j2, $m->getJoin('contact2'));
        static::assertTrue($m->hasJoin('contact2'));
        static::assertFalse($m->hasJoin('contact8'));

        $this->expectException(Exception::class); // TODO not implemented yet, see https://github.com/atk4/data/issues/803
        $this->expectExceptionMessage('Joining tables on non-id fields is not implemented yet');
        $j4 = $m->join('contact4.foo_id', ['masterField' => 'test_id', 'reverse' => true]);
        // static::assertTrue($this->getProtected($j4, 'reverse'));
        // static::assertSame('test_id', $this->getProtected($j4, 'masterField'));
        // static::assertSame('foo_id', $this->getProtected($j4, 'foreignField'));
    }

    public function testDirectionException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Joining tables on non-id fields is not implemented yet');
        $m->join('contact.foo_id', ['masterField' => 'test_id']);
    }

    public function testJoinSaving1(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
            ],
            'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123'],
            ],
        ]);

        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');

        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
            ],
        ], $this->getDb(['user', 'contact']));

        $user2 = $user->createEntity();
        $user2->set('name', 'Joe');
        $user2->set('contact_phone', '+321');
        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinSaving2(): void
    {
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ],
            'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('contact.test_id');
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');
        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 1],
            ],
        ], $this->getDb(['user', 'contact']));

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Peter');
        $user2->save();
        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 1],
                ['id' => 2, 'contact_phone' => null, 'test_id' => 2],
            ],
        ], $this->getDb(['user', 'contact']));

        $this->getConnection()->dsql()->table('contact')->where('id', 2)->mode('delete')->executeStatement();

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Sue');
        $user2->set('contact_phone', '+444');
        $user2->save();
        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Sue'],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 1],
                3 => ['id' => 3, 'contact_phone' => '+444', 'test_id' => 3],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinSaving3(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John', 'test_id' => 0],
            ],
            'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123'],
            ],
        ]);

        $user->addField('name');
        $j = $user->join('contact', ['masterField' => 'test_id']);
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');
        $user = $user->createEntity();

        $user->set('name', 'John');
        $user->set('contact_phone', '+123');

        $user->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'test_id' => 1],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinLoading(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('contact');
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        static::assertSame([
            'id' => 1, 'name' => 'John', 'contact_id' => 1, 'contact_phone' => '+123',
        ], $user2->get());

        $user2 = $user->load(3);
        static::assertSame([
            'id' => 3, 'name' => 'Joe', 'contact_id' => 2, 'contact_phone' => '+321',
        ], $user2->get());

        $user2 = $user2->unload();
        static::assertSame([
            'id' => null, 'name' => null, 'contact_id' => null, 'contact_phone' => null,
        ], $user2->get());

        static::assertNull($user->tryLoad(4));
    }

    public function testJoinUpdate(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        $user2->set('name', 'John 2');
        $user2->set('contact_phone', '+555');
        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb());

        $user2 = $user->load(1);
        $user2->set('name', 'XX');
        $user2->set('contact_phone', '+999');
        $user2 = $user->load(3);
        $user2->set('name', 'XX');
        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb());

        $user2->set('contact_phone', '+999');
        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+999'],
            ],
        ], $this->getDb());

        $user2 = $user->createEntity();
        $user2->set('name', 'YYY');
        $user2->set('contact_phone', '+777');
        $user2->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+999'],
                ['id' => 3, 'contact_phone' => '+777'],
            ],
        ], $this->getDb());
    }

    public function testJoinDelete(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+999'],
                ['id' => 3, 'contact_phone' => '+777'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        // TODO persist order is broken $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        $user = $user->load(1);
        $user->delete();

        static::assertSame([
            'user' => [
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                2 => ['id' => 2, 'contact_phone' => '+999'],
                ['id' => 3, 'contact_phone' => '+777'],
            ],
        ], $this->getDb());
    }

    public function testDoubleSaveHook(): void
    {
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ],
            'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('contact.test_id');
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        $user->onHook(Model::HOOK_AFTER_SAVE, static function (Model $m) {
            if ($m->get('contact_phone') !== '+123') {
                $m->set('contact_phone', '+123');
                $m->save();
            }
        });
        $user = $user->createEntity();
        $user->set('name', 'John');
        $user->save();

        static::assertSame([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 1],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testDoubleJoin(): void
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John 2', 'contact_id' => 100],
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
            ],
            'contact' => [
                100 => ['id' => 100, 'contact_phone' => '+555', 'country_id' => 1],
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
            ],
            'country' => [
                1 => ['id' => 1, 'name' => 'UK'],
                ['id' => 2, 'name' => 'US'],
                5 => ['id' => 5, 'name' => 'India'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $jContact = $user->join('contact');
        // TODO persist order is broken $this->createMigrator()->createForeignKey($jContact);
        $jContact->addField('contact_phone');
        $jCountry = $jContact->join('country');
        $this->createMigrator()->createForeignKey($jCountry);
        $jCountry->addField('country_name', ['actual' => 'name']);

        $user2 = $user->load(10);
        $user2->delete();

        $user2 = $user->loadBy('country_name', 'US');
        static::assertSame(30, $user2->getId());
        $user2->set('country_name', 'USA');
        $user2->save();

        $user2 = $user2->unload();
        static::assertFalse($user2->isLoaded());

        static::assertSame($user2->getModel()->getField('country_id')->getJoin(), $user2->getModel()->getField('contact_phone')->getJoin());

        $user->createEntity()->save(['name' => 'new', 'contact_phone' => '+000', 'country_name' => 'LV']);

        static::assertSame([
            'user' => [
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
                ['id' => 41, 'name' => 'new', 'contact_id' => 301],
            ],
            'contact' => [
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
                ['id' => 301, 'contact_phone' => '+000', 'country_id' => 6],
            ],
            'country' => [
                2 => ['id' => 2, 'name' => 'USA'],
                5 => ['id' => 5, 'name' => 'India'],
                ['id' => 6, 'name' => 'LV'],
            ],
        ], $this->getDb());
    }

    public function testDoubleReverseJoin(): void
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John 2', 'contact_id' => 100],
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
            ],
            'contact' => [
                100 => ['id' => 100, 'contact_phone' => '+555', 'country_id' => 1],
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
            ],
            'country' => [
                1 => ['id' => 1, 'name' => 'UK'],
                ['id' => 2, 'name' => 'US'],
                5 => ['id' => 5, 'name' => 'India'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer']);
        $user->addField('name');
        $j = $user->join('contact');
        // TODO persist order is broken $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');
        $c = $j->join('country');
        $this->createMigrator()->createForeignKey($c);
        $c->addField('country_name', ['actual' => 'name']);

        $user2 = $user->load(10);
        $user2->delete();

        $user = $user->loadBy('country_name', 'US');
        static::assertSame(30, $user->getId());

        static::assertSame([
            'user' => [
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
            ],
            'contact' => [
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
            ],
            'country' => [
                2 => ['id' => 2, 'name' => 'US'],
                5 => ['id' => 5, 'name' => 'India'],
            ],
        ], $this->getDb());
    }

    public function testJoinHasOneHasMany(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 10],
                ['id' => 2, 'name' => 'Jane', 'contact_id' => 11],
            ], 'contact' => [ // join User 1:1
                10 => ['id' => 10, 'phone_id' => 20],
                ['id' => 11, 'phone_id' => 21],
            ], 'phone' => [ // each Contact hasOne phone
                20 => ['id' => 20, 'number' => '+123'],
                ['id' => 21, 'number' => '+456'],
            ], 'token' => [ // each User hassMany Token
                30 => ['id' => 30, 'user_id' => 1, 'token' => 'ABC'],
                ['id' => 31, 'user_id' => 1, 'token' => 'DEF'],
                ['id' => 32, 'user_id' => 2, 'token' => 'GHI'],
            ], 'email' => [ // each Contact hasMany Email
                40 => ['id' => 40, 'contact_id' => 10, 'address' => 'john@foo.net'],
                ['id' => 41, 'contact_id' => 10, 'address' => 'johnny@foo.net'],
                ['id' => 42, 'contact_id' => 11, 'address' => 'jane@foo.net'],
            ],
        ]);

        // main user model joined to contact table
        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $user->addField('contact_id', ['type' => 'integer']);
        $j = $user->join('contact');
        $this->createMigrator()->createForeignKey($j);

        $user2 = $user->load(1);
        static::assertSame([
            'id' => 1, 'name' => 'John', 'contact_id' => 10,
        ], $user2->get());

        // hasOne phone model
        $phone = new Model($this->db, ['table' => 'phone']);
        $phone->addField('number');
        $refOne = $j->hasOne('phone_id', ['model' => $phone]); // hasOne on JOIN
        $this->createMigrator()->createForeignKey($refOne);
        $refOne->addField('number');

        $user2 = $user->load(1);
        static::assertSame([
            'id' => 1, 'name' => 'John', 'contact_id' => 10, 'phone_id' => 20, 'number' => '+123',
        ], $user2->get());

        // hasMany token model (uses default ourField, theirField)
        $token = new Model($this->db, ['table' => 'token']);
        $token->addField('user_id', ['type' => 'integer']);
        $token->addField('token');
        $refMany = $j->hasMany('Token', ['model' => $token]); // hasMany on JOIN (use default ourField, theirField)
        $this->createMigrator()->createForeignKey($refMany);

        $user2 = $user->load(1);
        static::assertSameExportUnordered([
            ['id' => 30, 'user_id' => 1, 'token' => 'ABC'],
            ['id' => 31, 'user_id' => 1, 'token' => 'DEF'],
        ], $user2->ref('Token')->export());

        $this->markTestIncompleteWhenCreateUniqueIndexIsNotSupportedByPlatform();

        // hasMany email model (uses custom ourField, theirField)
        $email = new Model($this->db, ['table' => 'email']);
        $email->addField('contact_id', ['type' => 'integer']);
        $email->addField('address');
        $refMany = $j->hasMany('Email', ['model' => $email, 'ourField' => 'contact_id', 'theirField' => 'contact_id']); // hasMany on JOIN (use custom ourField, theirField)
        $this->createMigrator()->createForeignKey($refMany);

        $user2 = $user->load(1);
        static::assertSameExportUnordered([
            ['id' => 40, 'contact_id' => 10, 'address' => 'john@foo.net'],
            ['id' => 41, 'contact_id' => 10, 'address' => 'johnny@foo.net'],
        ], $user2->ref('Email')->export());
    }

    public function testJoinReverseOneOnOne(): void
    {
        $this->setDb([
            'user' => [
                10 => ['id' => 10, 'name' => 'John'],
                20 => ['id' => 20, 'name' => 'Peter'],
            ],
            'detail' => [
                100 => ['id' => 100, 'my_user_id' => 10, 'notes' => 'first note'],
                200 => ['id' => 200, 'my_user_id' => 20, 'notes' => 'second note'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('detail.my_user_id', [
            // 'reverse' => true, // this will be reverse join by default
            // also no need to set these (will be done automatically), but still let's do that for test sake
            'masterField' => 'id',
            'foreignField' => 'my_user_id',
        ]);
        $this->createMigrator()->createForeignKey($j);
        $j->addField('notes');

        // load one record
        $m = $user->load(20);
        static::assertTrue($m->isLoaded());
        static::assertSame(['id' => 20, 'name' => 'Peter', 'notes' => 'second note'], $m->get());

        // update loaded record
        $m->save(['name' => 'Mark', 'notes' => '2nd note']);
        $m = $user->load(20);
        static::assertSame(['id' => 20, 'name' => 'Mark', 'notes' => '2nd note'], $m->get());

        // insert new record
        $m = $user->createEntity()->save(['name' => 'Emily', 'notes' => '3rd note']);
        $m = $user->load(21);
        static::assertTrue($m->isLoaded());
        static::assertSame(['id' => 21, 'name' => 'Emily', 'notes' => '3rd note'], $m->get());

        // now test reverse join defined differently
        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('detail', [ // here we just set foreign table name without dot and foreignField
            'reverse' => true, // and set it as revers join
            'foreignField' => 'my_user_id', // this is custome name so we have to set it here otherwise it will generate user_id
        ]);
        $j->addField('notes');

        // insert new record
        $m = $user->createEntity()->save(['name' => 'Olaf', 'notes' => '4th note']);
        $m = $user->load(22);
        static::assertSame(['id' => 22, 'name' => 'Olaf', 'notes' => '4th note'], $m->get());

        // now test reverse join with tableAlias and foreignAlias
        $user = new Model($this->db, ['table' => 'user', 'tableAlias' => 'u']);
        $user->addField('name');
        $j = $user->join('detail', [
            'reverse' => true,
            'foreignField' => 'my_user_id',
            'foreignAlias' => 'a',
        ]);
        $j->addField('notes');

        // insert new record
        $m = $user->createEntity()->save(['name' => 'Chris', 'notes' => '5th note']);
        $m = $user->load(23);
        static::assertSame(['id' => 23, 'name' => 'Chris', 'notes' => '5th note'], $m->get());
    }

    public function testJoinActualFieldNamesAndPrefix(): void
    {
        // currently we setup only integer PK and integer fields ending with "_id" name as unsigned
        // TODO improve Migrator so this hack is not needed
        $userForeignIdFieldName = 'uid';
        $contactForeignIdFieldName = 'cid';
        if ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $userForeignIdFieldName = 'user_id';
            $contactForeignIdFieldName = 'contact_id';
        }

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'first_name' => 'John', $contactForeignIdFieldName => 1],
                ['id' => 2, 'first_name' => 'Peter', $contactForeignIdFieldName => 1],
                ['id' => 3, 'first_name' => 'Joe', $contactForeignIdFieldName => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
            'salaries' => [
                1 => ['id' => 1, 'amount' => 123, $userForeignIdFieldName => 1],
                ['id' => 2, 'amount' => 456, $userForeignIdFieldName => 2],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id', ['type' => 'integer', 'actual' => $contactForeignIdFieldName]);
        $user->addField('name', ['actual' => 'first_name']);
        // normal join
        $j = $user->join('contact', ['prefix' => 'j1_']);
        $j->addField('phone', ['actual' => 'contact_phone']);
        // reverse join
        $j2 = $user->join('salaries.' . $userForeignIdFieldName, ['prefix' => 'j2_']);
        $j2->addField('salary', ['actual' => 'amount']);

        // update
        $user2 = $user->load(1);
        $user2->set('name', 'John 2');
        $user2->set('j1_phone', '+555');
        $user2->set('j2_salary', 111);
        $user2->save();

        static::{'assertEquals'}([
            'user' => [
                1 => ['id' => 1, 'first_name' => 'John 2', $contactForeignIdFieldName => 1],
                ['id' => 2, 'first_name' => 'Peter', $contactForeignIdFieldName => 1],
                ['id' => 3, 'first_name' => 'Joe', $contactForeignIdFieldName => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+321'],
            ],
            'salaries' => [
                1 => ['id' => 1, 'amount' => 111, $userForeignIdFieldName => 1],
                ['id' => 2, 'amount' => 456, $userForeignIdFieldName => 2],
            ],
        ], $this->getDb());

        // insert
        $user3 = $user->createEntity()->unload();
        $user3->set('name', 'Marvin');
        $user3->set('j1_phone', '+999');
        $user3->set('j2_salary', 222);
        $user3->save();

        static::{'assertEquals'}([
            'user' => [
                1 => ['id' => 1, 'first_name' => 'John 2', $contactForeignIdFieldName => 1],
                ['id' => 2, 'first_name' => 'Peter', $contactForeignIdFieldName => 1],
                ['id' => 3, 'first_name' => 'Joe', $contactForeignIdFieldName => 2],
                ['id' => 4, 'first_name' => 'Marvin', $contactForeignIdFieldName => 3],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                ['id' => 2, 'contact_phone' => '+321'],
                ['id' => 3, 'contact_phone' => '+999'],
            ],
            'salaries' => [
                1 => ['id' => 1, 'amount' => 111, $userForeignIdFieldName => 1],
                ['id' => 2, 'amount' => 456, $userForeignIdFieldName => 2],
                ['id' => 3, 'amount' => 222, $userForeignIdFieldName => 4],
            ],
        ], $this->getDb());
    }

    /**
     * @param array<string, mixed> $joinDefaults
     *
     * @return array{ Model, Model, Model }
     */
    protected function joinCustomForeignIdFieldSetup(array $joinDefaults = []): array
    {
        $masterModel = new Model($this->db, ['table' => 'user']);
        $masterModel->addField('name');
        $masterModel->addField('foo', ['type' => 'integer']);

        $joinedModel = new Model($this->db, ['table' => 'contact', 'idField' => 'uid']);
        $joinedModel->addField('bar', ['type' => 'integer']);
        $joinedModel->addField('contact_phone');

        $this->createMigrator($masterModel)->create();
        $this->createMigrator($joinedModel)->create();

        $masterModel->import([
            ['id' => 1, 'name' => 'John', 'foo' => 21],
            ['id' => 2, 'name' => 'Roman', 'foo' => 22],
        ]);

        $joinedModel->import([
            ['uid' => 1, 'bar' => 21, 'contact_phone' => '+123'],
            ['uid' => 2, 'bar' => 22, 'contact_phone' => '+200'],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('contact', array_merge([
            'masterField' => 'foo',
            'foreignField' => 'bar',
            'foreignIdField' => 'uid',
        ], $joinDefaults));
        $this->createMigrator()->createForeignKey($j);
        $j->addField('contact_phone');

        return [$masterModel, $joinedModel, $user];
    }

    public function testJoinCustomForeignIdFieldSaving(): void
    {
        [$masterModel, $joinedModel, $user] = $this->joinCustomForeignIdFieldSetup();

        $user = $user->load(1);
        $user->set('name', 'Karl');
        $user->set('contact_phone', '+321');
        $user->save();

        static::assertSame([
            ['id' => 1, 'name' => 'Karl', 'foo' => 21],
            ['id' => 2, 'name' => 'Roman', 'foo' => 22],
        ], $masterModel->export());
        static::assertSame([
            ['uid' => 1, 'bar' => 21, 'contact_phone' => '+321'],
            ['uid' => 2, 'bar' => 22, 'contact_phone' => '+200'],
        ], $joinedModel->export());
    }

    public function testJoinCustomForeignIdFieldDelete(): void
    {
        [$masterModel, $joinedModel, $user] = $this->joinCustomForeignIdFieldSetup();

        $user->delete(1);

        static::assertNull($masterModel->tryLoad(1));
        static::assertNull($joinedModel->tryLoad(1));
    }
}
