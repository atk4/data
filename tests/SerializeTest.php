<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;
use atk4\data\Persistence_Array;

class SerializeTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    public function testBasicSerialize()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'job');

        $f = $m->addField('data', ['serialize' => 'serialize']);

        $this->assertEquals(['data' => 'a:1:{s:3:"foo";s:3:"bar";}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));

        $f->serialize = 'json';
        $this->assertEquals(['data' => '{"foo":"bar"}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));
    }

    public function testSerializeIntegratinon()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'john', 'password' => 'john123'],
                2 => ['id' => 2, 'name' => 'sue', 'password' => password_hash('sue123', PASSWORD_DEFAULT)],
            ], ];
        $this->setDB($a);


        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');

        $f = $m->addField('name');
        $f = $m->addField('password', ['serialize' => 'paoussword']);
        $m->loadBy('name', 'john');
        $m['password'] = 'john321';

        var_Dump($m['password']);
        $m->save();
        var_Dump($m['password']);



        var_Dump($this->getDB());

        /*
        $this->assertEquals(['data' => 'a:1:{s:3:"foo";s:3:"bar";}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));

        $f->serialize = 'json';
        $this->assertEquals(['data' => '{"foo":"bar"}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));
         */
    }

    public function testIntegratino()
    {
        $data = [];
        $db = new Persistence_Array($data);
        $m = new Model($db);

        $f = $m->addField('data', ['serialize' => 'serialize']);
        $m->save(['data'=>'foo']);

        var_Dump($data);


        /*
        $this->assertEquals(['data' => 'a:1:{s:3:"foo";s:3:"bar";}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));

        $f->serialize = 'json';
        $this->assertEquals(['data' => '{"foo":"bar"}'], $db->typecastSaveRow($m, ['data' => ['foo' => 'bar']]));
         */
    }
}
