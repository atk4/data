<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_Array;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class PersistentArrayTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test constructor
     *
     */
    public function testLoadArray()
    {
        $a = [
            'user'=>[
                1=>['name'=>'John', 'surname'=>'Smith'],
                2=>['name'=>'Sarah', 'surname'=>'Jones'],
            ]
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Smith', $m['surname']);
    }


    public function testUpdateArray()
    {
        $a = [
            'user'=>[
                1=>['name'=>'John', 'surname'=>'Smith'],
                2=>['name'=>'Sarah', 'surname'=>'Jones'],
            ]
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $m['name']='Peter';
        $m->save();

        $m->load(2);
        $m['surname'] = 'Smith';
        $m->save();
        $m['surname'] = 'QQ';
        $m->save();

        $this->assertEquals([
            'user'=>[
                1=>['name'=>'Peter', 'surname'=>'Smith'],
                2=>['name'=>'Sarah', 'surname'=>'QQ'],
            ]
        ], $a);

        $m->unload();
        $m->set(['name'=>'Foo','surname'=>'Bar','other'=>'Baz']);
        $m->save();

        $this->assertEquals([
            'user'=>[
                1=>['name'=>'Peter', 'surname'=>'Smith'],
                2=>['name'=>'Sarah', 'surname'=>'QQ'],
                3=>['name'=>'Foo', 'surname'=>'Bar', 'id'=>3],
            ]
        ], $a);
    }

    public function testInsert()
    {
        $a = [
            'user'=>[
                1=>['name'=>'John', 'surname'=>'Smith'],
                2=>['name'=>'Sarah', 'surname'=>'Jones'],
            ]
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name'=>'Foo','surname'=>'Bar','other'=>'Baz']);

        $this->assertEquals([
            'user'=>[
                1=>['name'=>'John', 'surname'=>'Smith'],
                2=>['name'=>'Sarah', 'surname'=>'Jones'],
                3=>['name'=>'Foo', 'surname'=>'Bar', 'id'=>3],
            ]
        ], $a);
    }
}
