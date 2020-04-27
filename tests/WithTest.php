<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class WithTest extends \atk4\schema\PhpunitTestCase
{
    public function testWith()
    {
        $a = [
            'user' => [
                10 => ['id' => 10, 'name' => 'John', 'salary' => 2500],
                20 => ['id' => 20, 'name' => 'Peter', 'salary' => 4000],
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
        $m_user->addField('salary', ['type' => 'money']);

        $m_invoice = new Model($db, 'invoice');
        $m_invoice->addField('net', ['type' => 'money']);
        $m_invoice->hasOne('user_id', $m_user);
        $m_invoice->addCondition('net', '>', 100);

        // setup test model
        $m = clone $m_user;
        $m->addWith($m_invoice, 'i', ['user_id', 'net' => 'invoiced']); // add cursor
        $j_invoice = $m->join('i.user_id'); // join cursor
        $j_invoice->addField('invoiced');   // add field from joined cursor

        // tests
        $q = 'with "i" ("user_id","invoiced") as (select "user_id","net" from "invoice" where "net" > 100) select "user"."id","user"."name","user"."salary","_i"."invoiced" from "user" inner join "i" as "_i" on "_i"."user_id" = "user"."id"';
        $q = str_replace('"', $this->getEscapeChar(), $q);
        $this->assertEquals($q, $m->action('select')->getDebugQuery());
        $this->assertEquals(2, count($m->export()));
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
        $m1->addWith($m2, 't');
        $m1->addWith($m2, 't');
    }
}
