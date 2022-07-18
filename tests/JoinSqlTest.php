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

    public function testDirectionException(): void
    {
        $m = new Model($this->db, ['table' => 'user']);

        $this->expectException(Exception::class);
        $j = $m->join('contact.foo_id', ['master_field' => 'test_id']);
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

        $user->addField('contact_id');
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');

        $user2->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1]],
            'contact' => [1 => ['id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));

        $user2 = $user->createEntity();
        $user2->set('name', 'Joe');
        $user2->set('contact_phone', '+321');
        $user2->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinSaving2(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ],
            'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ],
        ]);
        $user->addField('name');
        $j = $user->join('contact.test_id');
        $j->addFields(['contact_phone']);

        $user2 = $user->createEntity();
        $user2->set('name', 'John');
        $user2->set('contact_phone', '+123');
        $user2->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Peter');
        $user2->save();
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
            ],
            'contact' => [
                1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'test_id' => 2, 'contact_phone' => null],
            ],
        ], $this->getDb(['user', 'contact']));

        $this->getConnection()->dsql()->table('contact')->where('id', 2)->mode('delete')->executeStatement();

        $user2->unload();
        $user2 = $user->createEntity();
        $user2->set('name', 'Sue');
        $user2->set('contact_phone', '+444');
        $user2->save();
        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Sue'],
            ],
            'contact' => [
                1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123'],
                3 => ['id' => 3, 'test_id' => 3, 'contact_phone' => '+444'],
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
        $j = $user->join('contact', ['master_field' => 'test_id']);
        $j->addField('contact_phone');
        $user = $user->createEntity();

        $user->set('name', 'John');
        $user->set('contact_phone', '+123');

        $user->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'test_id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'contact_phone' => '+123']],
        ], $this->getDb(['user', 'contact']));
    }

    public function testJoinLoading(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        $this->assertSame([
            'id' => 1, 'name' => 'John', 'contact_id' => '1', 'contact_phone' => '+123',
        ], $user2->get());

        $user2 = $user->load(3);
        $this->assertSame([
            'id' => 3, 'name' => 'Joe', 'contact_id' => '2', 'contact_phone' => '+321',
        ], $user2->get());

        $user2 = $user2->unload();
        $this->assertSame([
            'id' => null, 'name' => null, 'contact_id' => null, 'contact_phone' => null,
        ], $user2->get());

        $this->assertNull($user->tryLoad(4));
    }

    public function testJoinUpdate(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id');
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user2 = $user->load(1);
        $user2->set('name', 'John 2');
        $user2->set('contact_phone', '+555');
        $user2->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'Joe', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb());

        $user2 = $user->load(1);
        $user2->set('name', 'XX');
        $user2->set('contact_phone', '+999');
        $user2 = $user->load(3);
        $user2->set('name', 'XX');
        $user2->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
        ], $this->getDb());

        $user2->set('contact_phone', '+999');
        $user2->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
            ],
        ], $this->getDb());

        $user2 = $user->createEntity();
        $user2->set('name', 'YYY');
        $user2->set('contact_phone', '+777');
        $user2->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ],
        ], $this->getDb());
    }

    public function testJoinDelete(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John 2', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id');
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');

        $user = $user->load(1);
        $user->delete();

        $this->assertEquals([
            'user' => [
                2 => ['id' => 2, 'name' => 'Peter', 'contact_id' => 1],
                3 => ['id' => 3, 'name' => 'XX', 'contact_id' => 2],
                4 => ['id' => 4, 'name' => 'YYY', 'contact_id' => 3],
            ],
            'contact' => [
                2 => ['id' => 2, 'contact_phone' => '+999'],
                3 => ['id' => 3, 'contact_phone' => '+777'],
            ],
        ], $this->getDb());
    }

    public function testDoubleSaveHook(): void
    {
        $user = new Model($this->db, ['table' => 'user']);
        $this->setDb([
            'user' => [
                '_' => ['id' => 1, 'name' => 'John'],
            ],
            'contact' => [
                '_' => ['id' => 1, 'contact_phone' => '+123', 'test_id' => 0],
            ],
        ]);
        $user->addField('name');
        $j = $user->join('contact.test_id');
        $j->addField('contact_phone');

        $user->onHook(Model::HOOK_AFTER_SAVE, static function ($m) {
            if ($m->get('contact_phone') !== '+123') {
                $m->set('contact_phone', '+123');
                $m->save();
            }
        });
        $user = $user->createEntity();
        $user->set('name', 'John');
        $user->save();

        $this->assertEquals([
            'user' => [1 => ['id' => 1, 'name' => 'John']],
            'contact' => [1 => ['id' => 1, 'test_id' => 1, 'contact_phone' => '+123']],
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
                2 => ['id' => 2, 'name' => 'US'],
                5 => ['id' => 5, 'name' => 'India'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id');
        $user->addField('name');
        $jContact = $user->join('contact');
        $jContact->addField('contact_phone');
        $jCountry = $jContact->join('country');
        $jCountry->addField('country_name', ['actual' => 'name']);

        $user2 = $user->load(10);
        $user2->delete();

        $user2 = $user->loadBy('country_name', 'US');
        $this->assertEquals(30, $user2->getId());
        $user2->set('country_name', 'USA');
        $user2->save();

        $user2 = $user2->unload();
        $this->assertFalse($user2->isLoaded());

        $this->assertSame($user2->getModel()->getField('country_id')->getJoin(), $user2->getModel()->getField('contact_phone')->getJoin());

        $user->createEntity()->save(['name' => 'new', 'contact_phone' => '+000', 'country_name' => 'LV']);

        $this->assertEquals([
            'user' => [
                20 => ['id' => 20, 'name' => 'Peter', 'contact_id' => 100],
                30 => ['id' => 30, 'name' => 'XX', 'contact_id' => 200],
                40 => ['id' => 40, 'name' => 'YYY', 'contact_id' => 300],
                41 => ['id' => 41, 'name' => 'new', 'contact_id' => 301],
            ],
            'contact' => [
                200 => ['id' => 200, 'contact_phone' => '+999', 'country_id' => 2],
                300 => ['id' => 300, 'contact_phone' => '+777', 'country_id' => 5],
                301 => ['id' => 301, 'contact_phone' => '+000', 'country_id' => 6],
            ],
            'country' => [
                2 => ['id' => 2, 'name' => 'USA'],
                5 => ['id' => 5, 'name' => 'India'],
                6 => ['id' => 6, 'name' => 'LV'],
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
                2 => ['id' => 2, 'name' => 'US'],
                5 => ['id' => 5, 'name' => 'India'],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id');
        $user->addField('name');
        $j = $user->join('contact');
        $j->addField('contact_phone');
        $c = $j->join('country');
        $c->addFields(['country_name' => ['actual' => 'name']]);

        $user2 = $user->load(10);
        $user2->delete();

        $user = $user->loadBy('country_name', 'US');
        $this->assertEquals(30, $user->getId());

        $this->assertEquals([
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
                2 => ['id' => 2, 'name' => 'Jane', 'contact_id' => 11],
            ], 'contact' => [ // join User 1:1
                10 => ['id' => 10, 'phone_id' => 20],
                11 => ['id' => 11, 'phone_id' => 21],
            ], 'phone' => [ // each Contact hasOne phone
                20 => ['id' => 20, 'number' => '+123'],
                21 => ['id' => 21, 'number' => '+456'],
            ], 'token' => [ // each User hassMany Token
                30 => ['id' => 30, 'user_id' => 1, 'token' => 'ABC'],
                31 => ['id' => 31, 'user_id' => 1, 'token' => 'DEF'],
                32 => ['id' => 32, 'user_id' => 2, 'token' => 'GHI'],
            ], 'email' => [ // each Contact hasMany Email
                40 => ['id' => 40, 'contact_id' => 10, 'address' => 'john@foo.net'],
                41 => ['id' => 41, 'contact_id' => 10, 'address' => 'johnny@foo.net'],
                42 => ['id' => 42, 'contact_id' => 11, 'address' => 'jane@foo.net'],
            ],
        ]);

        // main user model joined to contact table
        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id');
        $user->addField('name');
        $j = $user->join('contact');

        $user2 = $user->load(1);
        $this->assertEquals([
            'id' => 1, 'name' => 'John', 'contact_id' => 10,
        ], $user2->get());

        // hasOne phone model
        $phone = new Model($this->db, ['table' => 'phone']);
        $phone->addField('number');
        $refOne = $j->hasOne('phone_id', ['model' => $phone]); // hasOne on JOIN
        $refOne->addField('number');

        $user2 = $user->load(1);
        $this->assertEquals([
            'id' => 1, 'name' => 'John', 'contact_id' => 10, 'phone_id' => 20, 'number' => '+123',
        ], $user2->get());

        // hasMany token model (uses default our_field, their_field)
        $token = new Model($this->db, ['table' => 'token']);
        $token->addField('user_id');
        $token->addField('token');
        $refMany = $j->hasMany('Token', ['model' => $token]); // hasMany on JOIN (use default our_field, their_field)

        $user2 = $user->load(1);
        $this->assertSameExportUnordered([
            ['id' => 30, 'user_id' => '1', 'token' => 'ABC'],
            ['id' => 31, 'user_id' => '1', 'token' => 'DEF'],
        ], $user2->ref('Token')->export());

        $this->markTestIncompleteWhenCreateUniqueIndexIsNotSupportedByPlatform();

        // hasMany email model (uses custom our_field, their_field)
        $email = new Model($this->db, ['table' => 'email']);
        $email->addField('contact_id');
        $email->addField('address');
        $refMany = $j->hasMany('Email', ['model' => $email, 'our_field' => 'contact_id', 'their_field' => 'contact_id']); // hasMany on JOIN (use custom our_field, their_field)

        $user2 = $user->load(1);
        $this->assertSameExportUnordered([
            ['id' => 40, 'contact_id' => '10', 'address' => 'john@foo.net'],
            ['id' => 41, 'contact_id' => '10', 'address' => 'johnny@foo.net'],
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
            'master_field' => 'id',
            'foreign_field' => 'my_user_id',
        ]);
        $j->addField('notes');

        // try load one record
        $m = $user->tryLoad(20);
        $this->assertTrue($m->isLoaded());
        $this->assertEquals(['id' => 20, 'name' => 'Peter', 'notes' => 'second note'], $m->get());

        // try to update loaded record
        $m->save(['name' => 'Mark', 'notes' => '2nd note']);
        $m = $user->tryLoad(20);
        $this->assertTrue($m->isLoaded());
        $this->assertEquals(['id' => 20, 'name' => 'Mark', 'notes' => '2nd note'], $m->get());

        // insert new record
        $m = $user->createEntity()->save(['name' => 'Emily', 'notes' => '3rd note']);
        $m = $user->tryLoad(21);
        $this->assertTrue($m->isLoaded());
        $this->assertEquals(['id' => 21, 'name' => 'Emily', 'notes' => '3rd note'], $m->get());

        // now test reverse join defined differently
        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('name');
        $j = $user->join('detail', [ // here we just set foreign table name without dot and foreign_field
            'reverse' => true, // and set it as revers join
            'foreign_field' => 'my_user_id', // this is custome name so we have to set it here otherwise it will generate user_id
        ]);
        $j->addField('notes');

        // insert new record
        $m = $user->createEntity()->save(['name' => 'Olaf', 'notes' => '4th note']);
        $m = $user->tryLoad(22);
        $this->assertTrue($m->isLoaded());
        $this->assertEquals(['id' => 22, 'name' => 'Olaf', 'notes' => '4th note'], $m->get());

        // now test reverse join with tableAlias and foreignAlias
        $user = new Model($this->db, ['table' => 'user', 'tableAlias' => 'u']);
        $user->addField('name');
        $j = $user->join('detail', [
            'reverse' => true,
            'foreign_field' => 'my_user_id',
            'foreignAlias' => 'a',
        ]);
        $j->addField('notes');

        // insert new record
        $m = $user->createEntity()->save(['name' => 'Chris', 'notes' => '5th note']);
        $m = $user->tryLoad(23);
        $this->assertTrue($m->isLoaded());
        $this->assertEquals(['id' => 23, 'name' => 'Chris', 'notes' => '5th note'], $m->get());
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
                2 => ['id' => 2, 'first_name' => 'Peter', $contactForeignIdFieldName => 1],
                3 => ['id' => 3, 'first_name' => 'Joe', $contactForeignIdFieldName => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
            'salaries' => [
                1 => ['id' => 1, 'amount' => 123, $userForeignIdFieldName => 1],
                2 => ['id' => 2, 'amount' => 456, $userForeignIdFieldName => 2],
            ],
        ]);

        $user = new Model($this->db, ['table' => 'user']);
        $user->addField('contact_id', ['actual' => $contactForeignIdFieldName]);
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

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'first_name' => 'John 2', $contactForeignIdFieldName => 1],
                2 => ['id' => 2, 'first_name' => 'Peter', $contactForeignIdFieldName => 1],
                3 => ['id' => 3, 'first_name' => 'Joe', $contactForeignIdFieldName => 2],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
            ],
            'salaries' => [
                1 => ['id' => 1, 'amount' => 111, $userForeignIdFieldName => 1],
                2 => ['id' => 2, 'amount' => 456, $userForeignIdFieldName => 2],
            ],
        ], $this->getDb());

        // insert
        $user3 = $user->createEntity()->unload();
        $user3->set('name', 'Marvin');
        $user3->set('j1_phone', '+999');
        $user3->set('j2_salary', 222);
        $user3->save();

        $this->assertEquals([
            'user' => [
                1 => ['id' => 1, 'first_name' => 'John 2', $contactForeignIdFieldName => 1],
                2 => ['id' => 2, 'first_name' => 'Peter', $contactForeignIdFieldName => 1],
                3 => ['id' => 3, 'first_name' => 'Joe', $contactForeignIdFieldName => 2],
                4 => ['id' => 4, 'first_name' => 'Marvin', $contactForeignIdFieldName => 3],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+555'],
                2 => ['id' => 2, 'contact_phone' => '+321'],
                3 => ['id' => 3, 'contact_phone' => '+999'],
            ],
            'salaries' => [
                1 => ['id' => 1, 'amount' => 111, $userForeignIdFieldName => 1],
                2 => ['id' => 2, 'amount' => 456, $userForeignIdFieldName => 2],
                3 => ['id' => 3, 'amount' => 222, $userForeignIdFieldName => 4],
            ],
        ], $this->getDb());
    }
}
