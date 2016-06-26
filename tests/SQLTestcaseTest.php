<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class SQLTestcaseTest extends SQLTestCase
{

    function testInit()
    {
        $this->setDB($q = ['user'=>[
            ['name'=>'John', 'surname'=>'Smith'],
            ['name'=>'Steve', 'surname'=>'Jobs']
        ]]);

        $q2 = $this->getDB('user');

        $this->setDB($q2);
        $q3 = $this->getDB('user');

        $this->assertEquals($q2, $q3);

        $this->assertEquals($q, $this->getDB('user', true));

    }

}
