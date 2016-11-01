<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_CSV;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class PersistentArrayTest extends \PHPUnit_Framework_TestCase
{

    public $file = 'atk-test.csv';

    function setDB($data) {
        $f = fopen($this->file, 'w');
        fputcsv($f, array_keys(current($data)));
        foreach($data as $row) {
            fputcsv($f, $row);
        }
        fclose($f);
    }

    function getDB() {
        $f = fopen($this->file, 'r');
        $keys = fgetcsv($f);
        $data = [];
        while($row = fgetcsv($f)) {
            $data[] = array_combine($keys, $row);
        }
        fclose($f);
        return $data;
    }


    /**
     * Test constructor.
     */
    public function testTestcase()
    {
        $data = [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ];

        $this->setDB($data);
        $data2 = $this->getDB();
        $this->assertEquals($data, $data2);
    }

    public function testLoadAny()
    {
        $data = [
                ['name' => 'John', 'surname' => 'Smith'],
                ['name' => 'Sarah', 'surname' => 'Jones'],
            ];

        $this->setDB($data);

        $p = new Persistence_CSV($this->file);
        $m = new Model($p);
        $m->addField('name');
        $m->addField('surname');
        $m->loadAny();

        $this->assertEquals('John', $m['name']);
        $this->assertEquals('Smith', $m['surname']);
    }
}
