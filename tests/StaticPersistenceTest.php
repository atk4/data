<?php

namespace atk4\data\tests;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class StaticPersistenceTest extends \atk4\core\PHPUnit_AgileTestCase
{
    /**
     * Test constructor.
     */
    public function testBasicStatic()
    {
        $p = new \atk4\data\Persistence_Static(['hello', 'world']);

        // default title field
        $m = new \atk4\data\Model($p);
        $m->load(1);
        $this->assertEquals('world', $m['name']);

        // custom title field and try loading from same static twice
        $m = new \atk4\data\Model($p); //, ['title_field' => 'foo']);
        $m->load(1);
        $this->assertEquals('world', $m['name']); // still 'name' here not 'foo'
    }

    public function testArrayOfArrays()
    {
        $p = new \atk4\data\Persistence_Static([['hello', 'xx', true], ['world', 'xy', false]]);
        $m = new \atk4\data\Model($p);

        $m->load(1);

        $this->assertEquals('world', $m['name']);
        $this->assertEquals('xy', $m['field1']);
        $this->assertEquals(false, $m['field2']);
    }

    public function testArrayOfHashes()
    {
        $p = new \atk4\data\Persistence_Static([['foo'=>'hello'], ['foo'=>'world']]);
        $m = new \atk4\data\Model($p);

        $m->load(1);

        $this->assertEquals('world', $m['foo']);
    }

    public function testIDArg()
    {
        $p = new \atk4\data\Persistence_Static([['id'=>20, 'foo'=>'hello'], ['id'=>21, 'foo'=>'world']]);
        $m = new \atk4\data\Model($p);

        $m->load(21);

        $this->assertEquals('world', $m['foo']);
    }

    public function testIDKey()
    {
        $p = new \atk4\data\Persistence_Static([20=>['foo'=>'hello'], 21=>['foo'=>'world']]);
        $m = new \atk4\data\Model($p);

        $m->load(21);

        $this->assertEquals('world', $m['foo']);
    }

    public function testEmpty()
    {
        $p = new \atk4\data\Persistence_Static([]);
        $m = new \atk4\data\Model($p);

        $m->tryLoadAny();

        $this->assertFalse($m->loaded());
    }

    public function testCustomField()
    {
        $p = new \atk4\data\Persistence_Static([['foo'=>'hello'], ['foo'=>'world']]);
        $m = new StaticPersistenceModel($p);

        $this->assertEquals('custom field', $m->getElement('foo')->caption);

        $p = new \atk4\data\Persistence_Static([['foo'=>'hello', 'bar'=>'world']]);
        $m = new StaticPersistenceModel($p);
        $this->assertEquals('foo', $m->title_field);
    }

    public function testTitleOrName()
    {
        $p = new \atk4\data\Persistence_Static([['foo'=>'hello', 'bar'=>'world']]);
        $m = new \atk4\data\Model($p);
        $this->assertEquals('foo', $m->title_field);

        $p = new \atk4\data\Persistence_Static([['foo'=>'hello', 'name'=>'x']]);
        $m = new \atk4\data\Model($p);
        $this->assertEquals('name', $m->title_field);

        $p = new \atk4\data\Persistence_Static([['foo'=>'hello', 'title'=>'x']]);
        $m = new \atk4\data\Model($p);
        $this->assertEquals('title', $m->title_field);
    }

    public function testFieldTypes()
    {
        $p = new \atk4\data\Persistence_Static([[
            'name'        => 'hello',
            'test_int'    => 123,
            'test_float'  => 123.45,
            'test_date'   => new \DateTime(),
            'test_array'  => ['a', 'b', 'c'],
            'test_object' => new \DateInterval('P1Y'),
            'test_str_1'  => 'abc',
            'test_str_2'  => '123',
            'test_str_3'  => '123.45',
        ]]);
        $m = new \atk4\data\Model($p);

        $this->assertEquals('integer', $m->getElement('test_int')->type);
        $this->assertEquals('float', $m->getElement('test_float')->type);
        $this->assertEquals('datetime', $m->getElement('test_date')->type);
        $this->assertEquals('array', $m->getElement('test_array')->type);
        $this->assertEquals('object', $m->getElement('test_object')->type);

        // string is default type, so it is null
        $this->assertNull($m->getElement('name')->type);
        $this->assertNull($m->getElement('test_str_1')->type);
        $this->assertNull($m->getElement('test_str_2')->type);
        $this->assertNull($m->getElement('test_str_3')->type);
    }
}

class StaticPersistenceModel extends \atk4\data\Model
{
    public $title_field = 'foo';

    public function init()
    {
        parent::init();

        $this->addField('foo', ['caption'=>'custom field']);
    }
}
