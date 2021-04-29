<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Doctrine\DBAL\Platforms\PostgreSQL94Platform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class ConditionSqlTest extends \Atk4\Schema\PhpunitTestCase
{
    public function testBasic()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender']);

        $mm = $m->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm = $m->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm2 = $mm->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertNull($mm2->get('name'));

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id","name","gender" from "user" where "gender" = :a',
                $mm->action('select')->render()
            );
        }

        $mm = clone $m;
        $mm->withId(2); // = addCondition(id, 2)
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));
    }

    public function testEntityNoScopeCloning()
    {
        $m = new Model($this->db, ['table' => 'user']);
        $scope = $m->scope();
        $this->assertSame($scope, $m->createEntity()->scope());
    }

    public function testEntityReloadWithDifferentIdException()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender']);

        $m = $m->tryLoad(1);
        $this->assertSame('John', $m->get('name'));
        \Closure::bind(function () use ($m) {
            $m->_entityId = 2;
        }, null, Model::class)();
        $this->expectException(\Atk4\Data\Exception::class);
        $this->expectExceptionMessageMatches('~entity.+different~');
        $m->reload();
    }

    public function testNull()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
                3 => ['id' => 3, 'name' => 'Null1', 'gender' => null],
                4 => ['id' => 4, 'name' => 'Null2', 'gender' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender']);

        $m->addCondition('gender', null);

        $nullCount = 0;
        foreach ($m as $user) {
            $this->assertNull($user->get('gender'));
            $this->assertStringContainsString('Null', $user->get('name'));

            ++$nullCount;
        }

        $this->assertSame(2, $nullCount);
    }

    public function testOperations()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender']);

        $mm = $m->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm = $m->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm2 = $mm->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertNull($mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', '!=', 'M');
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', '>', 1);
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', 'in', [1, 3]);
        $mm2 = $mm->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertNull($mm2->get('name'));
    }

    public function testExpressions1()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender']);

        $mm = $m->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm = $m->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[] > 1', [$mm->getField('id')]));
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[id] > 1'));
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));
    }

    public function testExpressions2()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender', 'surname']);

        $mm = $m->tryLoad(1);
        $this->assertSame('John', $mm->get('name'));
        $mm = $m->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), $m->getField('surname'));
        $mm2 = $mm->tryLoad(1);
        $this->assertNull($mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] != [surname]'));
        $mm2 = $mm->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertNull($mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), '!=', $m->getField('surname'));
        $mm2 = $mm->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        $this->assertNull($mm2->get('name'));
    }

    public function testExpressionJoin()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F', 'contact_id' => 2],
                3 => ['id' => 3, 'name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123 smiths'],
                2 => ['id' => 2, 'contact_phone' => '+321 sues'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'gender', 'surname']);

        $m->join('contact')->addField('contact_phone');

        $mm2 = $m->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $this->assertSame('+123 smiths', $mm2->get('contact_phone'));
        $mm2 = $m->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));
        $this->assertSame('+321 sues', $mm2->get('contact_phone'));
        $mm2 = $m->tryLoad(3);
        $this->assertSame('Peter', $mm2->get('name'));
        $this->assertSame('+123 smiths', $mm2->get('contact_phone'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm2 = $mm->tryLoad(1);
        $this->assertFalse($mm2->loaded());
        $mm2 = $mm->tryLoad(2);
        $this->assertSame('Sue', $mm2->get('name'));
        $this->assertSame('+321 sues', $mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(3);
        $this->assertFalse($mm2->loaded());

        $mm = clone $m;
        $mm->addCondition($mm->expr('\'+123 smiths\' = [contact_phone]'));
        $mm2 = $mm->tryLoad(1);
        $this->assertSame('John', $mm2->get('name'));
        $this->assertSame('+123 smiths', $mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(2);
        $this->assertNull($mm2->get('name'));
        $this->assertNull($mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(3);
        $this->assertSame('Peter', $mm2->get('name'));
        $this->assertSame('+123 smiths', $mm2->get('contact_phone'));
    }

    public function testArrayCondition()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Johhny'],
                3 => ['id' => 3, 'name' => 'Mary'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', ['John', 'Doe']);
        $this->assertCount(1, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', 'in', ['Johhny', 'Doe', 'Mary']);
        $this->assertCount(2, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', []); // this should not fail, always should be false
        $this->assertCount(0, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', 'not in', []); // this should not fail, always should be true
        $this->assertCount(3, $m->export());
    }

    public function testDateCondition()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m = $m->tryLoadBy('date', new \DateTime('08-12-1982'));
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testDateCondition2()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->addCondition('date', new \DateTime('08-12-1982'));
        $m = $m->loadOne();
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testDateConditionFailure()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $this->expectException(\Atk4\Dsql\Exception::class);
        $m = $m->tryLoadBy('name', new \DateTime('08-12-1982'));
    }

    public function testOrConditions()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);

        $u->addCondition(Model\Scope::createOr(
            ['name', 'John'],
            ['name', 'Peter'],
        ));

        $this->assertEquals(2, $u->action('count')->getOne());

        $u->addCondition(Model\Scope::createOr(
            ['name', 'Peter'],
            ['name', 'Joe'],
        ));
        $this->assertEquals(1, $u->action('count')->getOne());
    }

    /**
     * Test loadBy and tryLoadBy.
     * They should set only temporary condition.
     */
    public function testLoadBy()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = (new Model($this->db, ['table' => 'user']))->addFields(['name']);

        $u2 = $u->loadBy('name', 'John');
        $this->assertSame(['id' => 1, 'name' => 'John'], $u2->get());
        $this->assertTrue($u->scope()->isEmpty());
        $this->assertFalse($u->getField('name')->system); // should not set field as system
        $this->assertNull($u->getField('name')->default); // should not set field default value

        $u2 = $u->tryLoadBy('name', 'Joe');
        $this->assertSame(['id' => 3, 'name' => 'Joe'], $u2->get());
        $this->assertTrue($u->scope()->isEmpty());
        $this->assertFalse($u->getField('name')->system); // should not set field as system
        $this->assertNull($u->getField('name')->default); // should not set field default value
    }

    /**
     * Test LIKE condition.
     */
    public function testLikeCondition()
    {
        if ($this->getDatabasePlatform() instanceof PostgreSQL94Platform) {
            $this->markTestIncomplete('PostgreSQL does not support "column LIKE variable" syntax');
        }

        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'active' => 1, 'created' => '2020-01-01 15:00:30'],
                2 => ['id' => 2, 'name' => 'Peter', 'active' => 0, 'created' => '2019-05-20 12:13:14'],
                3 => ['id' => 3, 'name' => 'Joe', 'active' => 1, 'created' => '2019-07-15 09:55:05'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name', ['type' => 'string']);
        $u->addField('active', ['type' => 'boolean']);
        $u->addField('created', ['type' => 'datetime']);

        $t = (clone $u)->addCondition('created', 'like', '%19%');
        $this->assertCount(2, $t->export()); // only year 2019 records

        $t = (clone $u)->addCondition('active', 'like', '%1%');
        $this->assertCount(2, $t->export()); // only active records

        $t = (clone $u)->addCondition('active', 'like', '%0%');
        $this->assertCount(1, $t->export()); // only inactive records

        $t = (clone $u)->addCondition('active', 'like', '%999%');
        $this->assertCount(0, $t->export()); // bad value, so it will not match anything

        $t = (clone $u)->addCondition('active', 'like', '%ABC%');
        $this->assertCount(0, $t->export()); // bad value, so it will not match anything
    }
}
