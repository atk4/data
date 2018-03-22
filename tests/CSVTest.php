<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_CSV;
use atk4\data\tests\Model\Person as Person;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class CSVTest extends \atk4\core\PHPUnit_AgileTestCase
{
    public $file = 'atk-test.csv';
    public $file2 = 'atk-test-2.csv';

    public function setDB($data)
    {
        $f = fopen($this->file, 'w');
        fputcsv($f, array_keys(current($data)));
        foreach ($data as $row) {
            fputcsv($f, $row);
        }
        fclose($f);
    }

    public function tearDown()
    {
        // see: https://github.com/atk4/data/issues/271
        try {
            unlink($this->file);
            if (file_exists($this->file2)) {
                unlink($this->file2);
            }
        } catch (\Exception $e) {
        }
    }

    public function getDB()
    {
        $f = fopen($this->file, 'r');
        $keys = fgetcsv($f);
        $data = [];
        while ($row = fgetcsv($f)) {
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

    public function testLoadAnyException()
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
        $m->loadAny();

        $this->assertEquals('Sarah', $m['name']);
        $this->assertEquals('Jones', $m['surname']);

        $m->tryLoadAny();
        $this->assertFalse($m->loaded());
    }

    public function testPersistenceCopy()
    {
        $data = [
                ['name' => 'John', 'surname' => 'Smith', 'gender'=>'M'],
                ['name' => 'Sarah', 'surname' => 'Jones', 'gender'=>'F'],
            ];

        $this->setDB($data);

        $p = new Persistence_CSV($this->file);
        $p2 = new Persistence_CSV($this->file2);

        $m = new Person($p);

        $m2 = $m->withPersistence($p2);

        foreach ($m as $row) {
            $m2->save($m);
        }

        $this->assertEquals(
            file_get_contents($this->file2),
            file_get_contents($this->file)
        );
    }
}
