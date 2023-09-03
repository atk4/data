<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class ConditionSqlTest extends TestCase
{
    public function testBasic(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition('id', 2);
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));
    }

    public function testEntityNoScopeCloning(): void
    {
        $m = new Model($this->db, ['table' => 'user']);
        $scope = $m->scope();
        self::assertSame($scope, $m->createEntity()->getModel()->scope());

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected model, but instance is an entity');
        $m->createEntity()->scope();
    }

    public function testEntityReloadWithDifferentIdException(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $m = $m->load(1);
        self::assertSame('John', $m->get('name'));
        \Closure::bind(static function () use ($m) {
            $m->_entityId = 2;
        }, null, Model::class)();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model instance is an entity, ID cannot be changed to a different one');
        $m->reload();
    }

    public function testNull(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
                ['id' => 3, 'name' => 'Null1', 'gender' => null],
                ['id' => 4, 'name' => 'Null2', 'gender' => null],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $m->addCondition('gender', null);

        $nullCount = 0;
        foreach ($m as $user) {
            self::assertNull($user->get('gender'));
            self::assertStringContainsString('Null', $user->get('name'));

            ++$nullCount;
        }

        self::assertSame(2, $nullCount);
    }

    public function testOperations(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition('gender', '!=', 'M');
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', '>', 1);
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition('id', 'in', [1, 3]);
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);
    }

    public function testExpressions1(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[] > 1', [$mm->getField('id')]));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[id] > 1'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));
    }

    public function testExpressions2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');
        $m->addField('surname');

        $mm = $m->load(1);
        self::assertSame('John', $mm->get('name'));
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), $m->getField('surname'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] != [surname]'));
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), '!=', $m->getField('surname'));
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);
    }

    public function testExpressionJoin(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
                ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F', 'contact_id' => 2],
                ['id' => 3, 'name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
            ],
            'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123 smiths'],
                ['id' => 2, 'contact_phone' => '+321 sues'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('gender');
        $m->addField('surname');

        $m->join('contact')->addField('contact_phone');

        $mm2 = $m->load(1);
        self::assertSame('John', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));
        $mm2 = $m->load(2);
        self::assertSame('Sue', $mm2->get('name'));
        self::assertSame('+321 sues', $mm2->get('contact_phone'));
        $mm2 = $m->load(3);
        self::assertSame('Peter', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm2 = $mm->tryLoad(1);
        self::assertNull($mm2);
        $mm2 = $mm->load(2);
        self::assertSame('Sue', $mm2->get('name'));
        self::assertSame('+321 sues', $mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(3);
        self::assertNull($mm2);

        $mm = clone $m;
        $mm->addCondition($mm->expr('\'+123 smiths\' = [contact_phone]'));
        $mm2 = $mm->load(1);
        self::assertSame('John', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));
        $mm2 = $mm->tryLoad(2);
        self::assertNull($mm2);
        $mm2 = $mm->load(3);
        self::assertSame('Peter', $mm2->get('name'));
        self::assertSame('+123 smiths', $mm2->get('contact_phone'));
    }

    public function testArrayCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Johhny'],
                ['id' => 3, 'name' => 'Mary'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', ['John', 'Doe']);
        self::assertCount(1, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', 'in', ['Johhny', 'Doe', 'Mary']);
        self::assertCount(2, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', []); // this should not fail, should be always false
        self::assertCount(0, $m->export());

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addCondition('name', 'not in', []); // this should not fail, should be always true
        self::assertCount(3, $m->export());
    }

    public function testDateCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m = $m->loadBy('date', new \DateTime('08-12-1982'));
        self::assertSame('Sue', $m->get('name'));
    }

    public function testDateCondition2(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->addCondition('date', new \DateTime('08-12-1982'));
        $m = $m->loadOne();
        self::assertSame('Sue', $m->get('name'));
    }

    public function testDateConditionFailure(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $this->expectException(Exception::class);
        $m->tryLoadBy('name', new \DateTime('08-12-1982'));
    }

    public function testAndFromArrayCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $u->addCondition([
            ['name', 'like', 'J%'],
            ['name', 'like', '%e%'],
        ]);
        self::assertSameExportUnordered([
            ['id' => 3, 'name' => 'Joe'],
        ], $u->export());
    }

    public function testOrCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $u->addCondition(Model\Scope::createOr(
            ['name', 'John'],
            ['name', 'Peter'],
        ));
        self::assertSame(2, $u->executeCountQuery());

        $u->addCondition(Model\Scope::createOr(
            ['name', 'Peter'],
            ['name', 'Joe'],
        ));
        self::assertSame(1, $u->executeCountQuery());
    }

    public function testLoadByRestoreCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                ['id' => 2, 'name' => 'Peter'],
                ['id' => 3, 'name' => 'Joe'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name');

        $u2 = $u->loadBy('name', 'John');
        self::assertSame(['id' => 1, 'name' => 'John'], $u2->get());
        self::assertTrue($u->scope()->isEmpty());
        self::assertFalse($u->getField('name')->system); // should not set field as system
        self::assertNull($u->getField('name')->default); // should not set field default value

        $u2 = $u->tryLoadBy('name', 'Joe');
        self::assertSame(['id' => 3, 'name' => 'Joe'], $u2->get());
        self::assertTrue($u->scope()->isEmpty());
        self::assertFalse($u->getField('name')->system); // should not set field as system
        self::assertNull($u->getField('name')->default); // should not set field default value
    }

    public function testLikeCondition(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'active' => 1, 'created' => '2020-01-01 15:00:30'],
                ['id' => 2, 'name' => 'Peter', 'active' => 0, 'created' => '2019-05-20 12:13:14'],
                ['id' => 3, 'name' => 'Joe', 'active' => 1, 'created' => '2019-07-15 09:55:05'],
            ],
        ]);

        $u = new Model($this->db, ['table' => 'user']);
        $u->addField('name', ['type' => 'string']);
        $u->addField('active', ['type' => 'boolean']);
        $u->addField('created', ['type' => 'datetime']);

        $t = (clone $u)->addCondition('name', 'like', '%John%');
        self::assertCount(1, $t->export());

        $t = (clone $u)->addCondition('name', 'like', '%john%');
        self::assertCount(1, $t->export());

        $t = (clone $u)->addCondition('created', 'like', '%19%');
        self::assertCount(2, $t->export()); // only year 2019 records

        $t = (clone $u)->addCondition('active', 'like', '%1%');
        self::assertCount(2, $t->export()); // only active records

        $t = (clone $u)->addCondition('active', 'like', '%0%');
        self::assertCount(1, $t->export()); // only inactive records

        $t = (clone $u)->addCondition('active', 'like', '%999%');
        self::assertCount(0, $t->export()); // bad value, so it will not match anything

        $t = (clone $u)->addCondition('active', 'like', '%ABC%');
        self::assertCount(0, $t->export()); // bad value, so it will not match anything
    }
}
