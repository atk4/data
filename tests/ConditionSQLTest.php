<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ConditionSQLTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasic()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm->tryLoad(1);
        $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals(null, $mm['name']);

        if ($this->driver == 'sqlite') {
            $this->assertEquals(
                'select "id","name","gender" from "user" where "gender" = :a',
                $mm->action('select')->render()
            );
        }

        $mm = clone $m;
        $mm->withID(2); // = addCondition(id, 2)
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);
    }

    public function testOperations()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition('gender', 'M');
        $mm->tryLoad(1);
        $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals(null, $mm['name']);

        $mm = clone $m;
        $mm->addCondition('gender', '!=', 'M');
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition('id', '>', 1);
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition('id', 'in', [1, 3]);
        $mm->tryLoad(1);
        $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals(null, $mm['name']);
    }

    public function testExpressions1()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[] > 1', [$mm->getField('id')]));
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[id] > 1'));
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);
    }

    public function testExpressions2()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender', 'surname']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), $m->getField('surname'));
        $mm->tryLoad(1);
        $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] != [surname]'));
        $mm->tryLoad(1);
        $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals(null, $mm['name']);

        $mm = clone $m;
        $mm->addCondition($m->getField('name'), '!=', $m->getField('surname'));
        $mm->tryLoad(1);
        $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2);
        $this->assertEquals(null, $mm['name']);
    }

    public function testExpressionJoin()
    {
        if ($this->driver == 'pgsql') {
            $this->markTestIncomplete('This test is not supported on PostgreSQL');
        }

        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'gender' => 'F', 'contact_id' => 2],
                3 => ['id' => 3, 'name' => 'Peter', 'surname' => 'Smith', 'gender' => 'M', 'contact_id' => 1],
            ], 'contact' => [
                1 => ['id' => 1, 'contact_phone' => '+123 smiths'],
                2 => ['id' => 2, 'contact_phone' => '+321 sues'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addFields(['name', 'gender', 'surname']);

        $m->join('contact')->addField('contact_phone');

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $this->assertEquals('+123 smiths', $m['contact_phone']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);
        $this->assertEquals('+321 sues', $m['contact_phone']);
        $m->tryLoad(3);
        $this->assertEquals('Peter', $m['name']);
        $this->assertEquals('+123 smiths', $m['contact_phone']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm->tryLoad(1);
        $this->assertEquals(false, $mm->loaded());
        $mm->tryLoad(2);
        $this->assertEquals('Sue', $mm['name']);
        $this->assertEquals('+321 sues', $mm['contact_phone']);
        $mm->tryLoad(3);
        $this->assertEquals(false, $mm->loaded());

        $mm = clone $m;
        $mm->addCondition($mm->expr('"+123 smiths" = [contact_phone]'));
        $mm->tryLoad(1);
        $this->assertEquals('John', $mm['name']);
        $this->assertEquals('+123 smiths', $mm['contact_phone']);
        $mm->tryLoad(2);
        $this->assertEquals(null, $mm['name']);
        $this->assertEquals(null, $mm['contact_phone']);
        $mm->tryLoad(3);
        $this->assertEquals('Peter', $mm['name']);
        $this->assertEquals('+123 smiths', $mm['contact_phone']);
    }

    public function testArrayCondition()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Johhny'],
                3 => ['id' => 3, 'name' => 'Mary'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', ['John', 'Doe']);
        $this->assertEquals(1, count($m->export()));

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', 'in', ['Johhny', 'Doe', 'Mary']);
        $this->assertEquals(2, count($m->export()));

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', []); // this should not fail, always should be false
        $this->assertEquals(0, count($m->export()));

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addCondition('name', 'not', []); // this should not fail, always should be true
        $this->assertEquals(3, count($m->export()));
    }

    public function testDateCondition()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->tryLoadBy('date', new \DateTime('08-12-1982'));
        $this->assertEquals('Sue', $m['name']);
    }

    public function testDateCondition2()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->addCondition('date', new \DateTime('08-12-1982'));
        $m->loadAny();
        $this->assertEquals('Sue', $m['name']);

        $m->addCondition([['date', new \DateTime('08-12-1982')]]);
        $m->loadAny();
        $this->assertEquals('Sue', $m['name']);
    }

    /**
     * @expectedException        \Exception
     */
    public function testDateConditionFailure()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'date' => '1981-12-08'],
                2 => ['id' => 2, 'name' => 'Sue', 'date' => '1982-12-08'],
            ], ];
        $this->setDB($a);

        $m = new Model($this->db, 'user');
        $m->addField('name');
        $m->addField('date', ['type' => 'date']);

        $m->tryLoadBy('name', new \DateTime('08-12-1982'));
    }

    /**
     * Tests OR conditions.
     */
    public function testOrConditions()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
        ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name']);

        $u->addCondition([
            ['name', 'John'],
            ['name', 'Peter'],
        ]);

        $this->assertEquals(2, $u->action('count')->getOne());

        $u->addCondition([
            ['name', 'Peter'],
            ['name', 'Joe'],
        ]);
        $this->assertEquals(1, $u->action('count')->getOne());
    }

    /**
     * Test loadBy and tryLoadBy.
     * They should set only temporary condition.
     */
    public function testLoadBy()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John'],
                2 => ['id' => 2, 'name' => 'Peter'],
                3 => ['id' => 3, 'name' => 'Joe'],
            ],
        ];
        $this->setDB($a);

        $u = (new Model($this->db, 'user'))->addFields(['name']);

        $u->loadBy('name', 'John');
        $this->assertEquals([], $u->conditions); // should be no conditions
        $this->assertFalse($u->getField('name')->system); // should not set field as system
        $this->assertNull($u->getField('name')->default); // should not set field default value

        $u->tryLoadBy('name', 'John');
        $this->assertEquals([], $u->conditions); // should be no conditions
        $this->assertFalse($u->getField('name')->system); // should not set field as system
        $this->assertNull($u->getField('name')->default); // should not set field default value
    }
}
