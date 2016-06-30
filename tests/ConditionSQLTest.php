<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ConditionSQLTest extends SQLTestCase
{

    public function testBasic()
    {
        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John', 'gender'=>'M'],
                2=>['id'=>2, 'name'=>'Sue', 'gender'=>'F'],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name','gender']);

        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);

        $m->addCondition('gender','M');
        $m->tryLoad(1);
        $this->assertEquals('John', $m['name']);
        $m->tryLoad(2);
        $this->assertEquals(null, $m['name']);

        $this->assertEquals(
            'select `id`,`name`,`gender` from `user` where `gender` = :a',
            $m->action('select')->render()
        );
    }

    public function testOperations()
    {
        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John', 'gender'=>'M'],
                2=>['id'=>2, 'name'=>'Sue', 'gender'=>'F'],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name','gender']);

        $m->tryLoad(1); $this->assertEquals('John', $m['name']);
        $m->tryLoad(2); $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition('gender','M');
        $mm->tryLoad(1); $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2); $this->assertEquals(null, $mm['name']);

        $mm = clone $m;
        $mm->addCondition('gender','!=','M');
        $mm->tryLoad(1); $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2); $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition('id','>',1);
        $mm->tryLoad(1); $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2); $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition('id','in',[1,3]);
        $mm->tryLoad(1); $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2); $this->assertEquals(null, $mm['name']);
    }

    public function testExpressions1()
    {
        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John', 'gender'=>'M'],
                2=>['id'=>2, 'name'=>'Sue', 'gender'=>'F'],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name','gender']);

        $m->tryLoad(1); $this->assertEquals('John', $m['name']);
        $m->tryLoad(2); $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[] > 1', [$mm->getElement('id')]));
        $mm->tryLoad(1); $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2); $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[id] > 1'));
        $mm->tryLoad(1); $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2); $this->assertEquals('Sue', $mm['name']);
    }

    public function testExpressions2()
    {
        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John', 'surname'=>'Smith', 'gender'=>'M'],
                2=>['id'=>2, 'name'=>'Sue', 'surname'=>'Sue', 'gender'=>'F'],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name','gender','surname']);

        $m->tryLoad(1); $this->assertEquals('John', $m['name']);
        $m->tryLoad(2); $this->assertEquals('Sue', $m['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] = [surname]'));
        $mm->tryLoad(1); $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2); $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition($m->getElement('name'), $m->getElement('surname'));
        $mm->tryLoad(1); $this->assertEquals(null, $mm['name']);
        $mm->tryLoad(2); $this->assertEquals('Sue', $mm['name']);

        $mm = clone $m;
        $mm->addCondition($mm->expr('[name] != [surname]'));
        $mm->tryLoad(1); $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2); $this->assertEquals(null, $mm['name']);

        $mm = clone $m;
        $mm->addCondition($m->getElement('name'), '!=', $m->getElement('surname'));
        $mm->tryLoad(1); $this->assertEquals('John', $mm['name']);
        $mm->tryLoad(2); $this->assertEquals(null, $mm['name']);
    }
}
