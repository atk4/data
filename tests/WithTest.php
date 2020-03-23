<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class WithTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testWith()
    {
        $a = [
            'user' => [
                10 => ['id' => 10, 'name' => 'John', 'salary' => 1000],
                20 => ['id' => 20, 'name' => 'Peter', 'salary' => 1500],
            ], 'quote' => [
                1 => ['id' => 1, 'net' => 100, 'user_id' => 10],
                2 => ['id' => 2, 'net' => 350, 'user_id' => 20],
                3 => ['id' => 3, 'net' => 75, 'user_id' => 10],
            ], 'invoice' => [
                1 => ['id' => 1, 'net' => 500, 'user_id' => 10],
                2 => ['id' => 2, 'net' => 200, 'user_id' => 20],
                3 => ['id' => 3, 'net' => 100, 'user_id' => 20],
            ], ];
        $this->setDB($a);
        $db = new Persistence\SQL($this->db->connection);

        // setup models
        $m_user = new Model($db, 'user');
        $m_user->addField('name');
        $m_user->addField('salary', ['type'=>'money']);

        $m_quote = new Model($db, 'quote');
        $m_quote->addField('net', ['type'=>'money']);
        $m_quote->hasOne('user_id', $m_user);
        // @todo How to add SUM + GROUP BY here ????

        $m_invoice = new Model($db, 'invoice');
        $m_invoice->addField('net', ['type'=>'money']);
        $m_invoice->hasOne('user_id', $m_user);
        // @todo How to add SUM + GROUP BY here ????

        // setup test model
        $m = clone $m_user;
        $m->addWith($m_quote, 'q', ['user_id','net'=>'quoted'], false);
        //$m->addWith($m_invoice, 'i', ['user_id','invoiced']);

        $j_user = $m->join('q.user_id'); // join cursors
        $j_user->addField('quoted');
        

        // tests
        echo $m->action('select')->getDebugQuery();

        var_dump($m->export());
    
    }
    
    /**
     * Alias should be unique.
     *
     * @expectedException Exception
     */
    public function testUniqueAliasException()
    {
        $m1 = new Model();
        $m2 = new Model();
        $m1->addWith($m2,'t');
        $m1->addWith($m2,'t');
    }
}
