<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_Array;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class LoadAndSaveTest extends \PHPUnit_Framework_TestCase
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

}
