<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\Persistence_Array;
use atk4\data\tests\Model\Female as Female;
use atk4\data\tests\Model\Male as Male;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class PersistentArrayTest extends \atk4\core\PHPUnit_AgileTestCase
{
    /**
     * Test constructor.
     */
    public function testLoadArray()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->unload();
        $this->assertFalse($m->loaded());

        $m->tryLoadAny();
        $this->assertTrue($m->loaded());

        $m->load(2);
        $this->assertEquals('Jones', $m['surname']);
        $m['surname'] = 'Smith';
        $m->save();

        $m->load(1);
        $this->assertEquals('John', $m['name']);

        $m->load(2);
        $this->assertEquals('Smith', $m['surname']);
    }

    public function testSaveAs()
    {
        //$this->markTestSkipped('TODO: see #146');
        $a = [
            'person' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ];

        $p = new Persistence_Array($a);

        $m = new Male($p);
        $m->load(1);
        $m->saveAs(new Female());
        $m->delete();

        $this->assertEquals([
            'person' => [
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
                3 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F', 'id'=>3],
            ],
        ], $a);
    }

    public function testSaveAndUnload()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'M'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ];

        $p = new Persistence_Array($a);
        $m = new Male($p, 'user');

        $m->load(1);
        $this->assertTrue($m->loaded());
        $m['gender'] = 'F';
        $m->saveAndUnload();
        $this->assertFalse($m->loaded());

        $m = new Female($p, 'user');
        $m->load(1);
        $this->assertTrue($m->loaded());

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith', 'gender' => 'F'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones', 'gender' => 'F'],
            ],
        ], $a);
    }

    public function testUpdateArray()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->load(1);
        $m['name'] = 'Peter';
        $m->save();

        $m->load(2);
        $m['surname'] = 'Smith';
        $m->save();
        $m['surname'] = 'QQ';
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
            ],
        ], $a);

        $m->unload();
        $m->set(['name' => 'Foo', 'surname' => 'Bar']);
        $m->save();

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'Peter', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'QQ'],
                3 => ['name' => 'Foo', 'surname' => 'Bar', 'id' => 3],
            ],
        ], $a);
    }

    public function testInsert()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $m->insert(['name' => 'Foo', 'surname' => 'Bar']);

        $this->assertEquals([
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
                3 => ['name' => 'Foo', 'surname' => 'Bar', 'id' => 3],
            ],
        ], $a);
    }

    public function testIterator()
    {
        $a = [
            'user' => [
                1 => ['name' => 'John', 'surname' => 'Smith'],
                2 => ['name' => 'Sarah', 'surname' => 'Jones'],
            ],
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p, 'user');
        $m->addField('name');
        $m->addField('surname');

        $output = '';

        foreach ($m as $row) {
            $output .= $row['name'];
        }

        $this->assertEquals('JohnSarah', $output);
    }

    /**
     * Test short format.
     */
    public function testShortFormat()
    {
        $a = [
            1 => ['name' => 'John', 'surname' => 'Smith'],
            2 => ['name' => 'Sarah', 'surname' => 'Jones'],
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p);
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

    /**
     * Non SQL persistences don't support action() method.
     *
     * @expectedException Exception
     */
    public function testActionNotSupportedException()
    {
        $a = [
            1 => ['name' => 'John'],
            2 => ['name' => 'Sarah'],
        ];

        $p = new Persistence_Array($a);
        $m = new Model($p);
        $m->addField('name');

        $m->action('count');
    }

    /**
     * Some persistences don't support tryLoad() method.
     *
     * @expectedException Exception
     */
    public function testTryLoadNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->tryLoad(1);
    }

    /**
     * Some persistences don't support loadAny() method.
     *
     * @expectedException Exception
     */
    public function testLoadAnyNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->loadAny();
    }

    /**
     * Some persistences don't support tryLoadAny() method.
     *
     * @expectedException Exception
     */
    public function testTryLoadAnyNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->tryLoadAny();
    }

    /**
     * Some persistences don't support export() method.
     *
     * @expectedException Exception
     */
    public function testExportNotSupportedException()
    {
        $m = new Model(new Persistence());
        $m->export();
    }
}
