<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Model;
use atk4\data\Persistence;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class JoinTest extends AtkPhpunit\TestCase
{
    public function testBasicJoin()
    {
        $a = ['user' => [], 'contact' => []];
        $db = new Persistence\Array_($a);
        $m = new Model($db, 'user');
        $m->addField('name');

        $j = $m->join('contact');
        $j->addField('contact_phone');

        $m['name'] = 'John';
        $m['contact_phone'] = '+123';
        $m->save();

        /*
        $this->assertEquals([
            'user'=>[
                1=>['name'=>'John','contact_id'=>1]
            ],'contact'=>[
                1=>['contact_phone'=>'+123'
            ]]], $a);
        )*/
    }

    public function testReverseJoin()
    {
        $a = [];
        $db = new Persistence\Array_($a);
        $m = new Model($db);
        $m->addField('name');
    }

    public function testMultipleJoins()
    {
    }

    public function testTrickyCases()
    {
        $a = [];
        $db = new Persistence\Array_($a);
        $m = new Model($db);

        // tricky cases to testt
        //
        //$m->join('foo.bar', ['master_field'=>'baz']);
        // foreign_table = 'foo.bar'
    }
}
