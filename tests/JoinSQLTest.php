<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class JoinSQLTest extends SQLTestCase
{

    public function testDirection()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');

        $j = $m->join('contact');
        $this->assertEquals(false, $this->getProtected($j,'reverse'));
        $this->assertEquals('contact_id', $this->getProtected($j,'master_field'));
        $this->assertEquals('id', $this->getProtected($j,'foreign_field'));

        $j = $m->join('contact2.test_id');
        $this->assertEquals(true, $this->getProtected($j,'reverse'));
        $this->assertEquals('id', $this->getProtected($j,'master_field'));
        $this->assertEquals('test_id', $this->getProtected($j,'foreign_field'));

        $j = $m->join('contact3', 'test_id');
        $this->assertEquals(false, $this->getProtected($j,'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j,'master_field'));
        $this->assertEquals('id', $this->getProtected($j,'foreign_field'));

        $j = $m->join('contact3', ['test_id']);
        $this->assertEquals(false, $this->getProtected($j,'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j,'master_field'));
        $this->assertEquals('id', $this->getProtected($j,'foreign_field'));

        $j = $m->join('contact4.foo_id', ['test_id', 'reverse'=>true]);
        $this->assertEquals(true, $this->getProtected($j,'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j,'master_field'));
        $this->assertEquals('foo_id', $this->getProtected($j,'foreign_field'));
    }

    /**
     * @expectedException Exception
     */
    public function testDirection2()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $j = $m->join('contact4.foo_id', 'test_id');
        $this->assertEquals(true, $this->getProtected($j,'reverse'));
        $this->assertEquals('test_id', $this->getProtected($j,'master_field'));
        $this->assertEquals('foo_id', $this->getProtected($j,'foreign_field'));

    }

    public function testJoinLoading()
    {
        $a = [
            'user'=>[
                1=>['id'=>1, 'name'=>'John','contact_id'=>1],
                2=>['id'=>2, 'name'=>'Peter','contact_id'=>1],
                3=>['id'=>3, 'name'=>'Joe','contact_id'=>2],
            ], 'contact'=>[
                1=>['id'=>1, 'contact_phone'=>'+123'],
                2=>['id'=>2, 'contact_phone'=>'+321'],
            ]];

        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u->load(1);

        $this->assertEquals([
            'name'=>'John','contact_id'=>1, 'contact_phone'=>'+123', 'id'=>1
        ], $m_u->get());

        $m_u->load(3);
        $this->assertEquals([
            'name'=>'Joe','contact_id'=>2, 'contact_phone'=>'+321', 'id'=>3
        ], $m_u->get());

        $m_u->tryLoad(4);
        $this->assertEquals([
            'name'=>null,'contact_id'=>null, 'contact_phone'=>null, 'id'=>null
        ], $m_u->get());
    }


    public function testJoinSaving1()
    {
        $a = [
            'user'=>[
                '_'=>['id'=>1, 'name'=>'John','contact_id'=>1],
            ], 'contact'=>[
                '_'=>['id'=>1, 'contact_phone'=>'+123'],
            ]];

        $db = new Persistence_SQL($this->db->connection);
        $m_u = new Model($db, 'user');
        $this->setDB($a);

        $m_u->addField('contact_id');
        $m_u->addField('name');
        $j = $m_u->join('contact');
        $j->addField('contact_phone');

        $m_u['name']='John';
        $m_u['contact_phone']='+123';

        $m_u->save();

        $this->assertEquals([
            'user'=>[1=>['id'=>1, 'name'=>'John','contact_id'=>1]],
            'contact'=>[1=>['id'=>1, 'contact_phone'=>'+123']]
        ], $this->getDB('user,contact'));

        $m_u->unload();
        $m_u['name']='Peter';
        $m_u['contact_id']=1;
        $m_u->save();
        $m_u->unload();

        $this->assertEquals([
            'user'=>[
                1=>['id'=>1, 'name'=>'John','contact_id'=>1],
                2=>['id'=>2, 'name'=>'Peter','contact_id'=>1],
            ], 'contact'=>[
                1=>['id'=>1, 'contact_phone'=>'+123']
            ]
        ], $this->getDB('user,contact'));

        $m_u['name']='Joe';
        $m_u['contact_phone']='+321';
        $m_u->save();

        $this->assertEquals([
            'user'=>[
                1=>['id'=>1, 'name'=>'John','contact_id'=>1],
                2=>['id'=>2, 'name'=>'Peter','contact_id'=>1],
                3=>['id'=>3, 'name'=>'Joe','contact_id'=>2],
            ], 'contact'=>[
                1=>['id'=>1, 'contact_phone'=>'+123'],
                2=>['id'=>2, 'contact_phone'=>'+321'],
            ]
        ], $this->getDB('user,contact'));
    }

}
