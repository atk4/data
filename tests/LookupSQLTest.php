<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 *
 * ATK Data has an option to lookup ID values if their "lookup" values are specified.
 */
class LookupSQLTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasicLookup()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'code'=>'JN'],
                2 => ['id' => 2, 'name' => 'Peter', 'code'=>'PT'],
                3 => ['id' => 3, 'name' => 'Joe', 'code'=>'JN'],
            ], 'order' => [
                ['amount' => '20', 'user_id' => 1],
                /*
                ['amount' => '15', 'user_id' => 2],
                ['amount' => '5', 'user_id' => 1],
                ['amount' => '3', 'user_id' => 1],
                ['amount' => '8', 'user_id' => 3],
                 */
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $u = (new Model($db, 'user'))->addFields(['name']);
        $o = (new Model($db, 'order'))->addFields(['amount']);

        $o->hasOne('user_id')->withTitle()->addField('code');

        $o->insert([
            'amount'=>15,
            'user_id'=>2
        ]);

        // lookup by user title
        $o->insert([
            'amount'=>15,
            'user'=>'Peter'
        ]);

        // lookup by code
        $o->insert([
            'amount'=>15,
            'code'=>'JN'
        ]);

        var_Dump($this->getDB()['order']);
    }
}
