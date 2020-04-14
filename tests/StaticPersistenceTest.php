<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;

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
        $p = new Persistence\Static_(['hello', 'world']);

        // default title field
        $m = new Model($p);
        $m->load(1);
        $this->assertEquals('world', $m['name']);

        // custom title field and try loading from same static twice
        $m = new Model($p); //, ['title_field' => 'foo']);
        $m->load(1);
        $this->assertEquals('world', $m['name']); // still 'name' here not 'foo'
    }

    public function testArrayOfArrays()
    {
        $p = new Persistence\Static_([['hello', 'xx', true], ['world', 'xy', false]]);
        $m = new Model($p);

        $m->load(1);

        $this->assertEquals('world', $m['name']);
        $this->assertEquals('xy', $m['field1']);
        $this->assertEquals(false, $m['field2']);
    }

    public function testArrayOfHashes()
    {
        $p = new Persistence\Static_([['foo'=>'hello'], ['foo'=>'world']]);
        $m = new Model($p);

        $m->load(1);

        $this->assertEquals('world', $m['foo']);
    }

    public function testIDArg()
    {
        $p = new Persistence\Static_([['id'=>20, 'foo'=>'hello'], ['id'=>21, 'foo'=>'world']]);
        $m = new Model($p);

        $m->load(21);

        $this->assertEquals('world', $m['foo']);
    }

    public function testIDKey()
    {
        $p = new Persistence\Static_([20=>['foo'=>'hello'], 21=>['foo'=>'world']]);
        $m = new Model($p);

        $m->load(21);

        $this->assertEquals('world', $m['foo']);
    }

    public function testEmpty()
    {
        $p = new Persistence\Static_([]);
        $m = new Model($p);

        $m->tryLoadAny();

        $this->assertFalse($m->loaded());
    }

    public function testCustomField()
    {
        $p = new Persistence\Static_([['foo'=>'hello'], ['foo'=>'world']]);
        $m = new StaticPersistenceModel($p);

        $this->assertEquals('custom field', $m->getField('foo')->caption);

        $p = new Persistence\Static_([['foo'=>'hello', 'bar'=>'world']]);
        $m = new StaticPersistenceModel($p);
        $this->assertEquals('foo', $m->title_field);
    }

    public function testTitleOrName()
    {
        $p = new Persistence\Static_([['foo'=>'hello', 'bar'=>'world']]);
        $m = new Model($p);
        $this->assertEquals('foo', $m->title_field);

        $p = new Persistence\Static_([['foo'=>'hello', 'name'=>'x']]);
        $m = new Model($p);
        $this->assertEquals('name', $m->title_field);

        $p = new Persistence\Static_([['foo'=>'hello', 'title'=>'x']]);
        $m = new Model($p);
        $this->assertEquals('title', $m->title_field);
    }

    public function testFieldTypes()
    {
        $p = new Persistence\Static_([[
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
        $m = new Model($p);

        $this->assertEquals('integer', $m->getField('test_int')->type);
        $this->assertEquals('float', $m->getField('test_float')->type);
        $this->assertEquals('datetime', $m->getField('test_date')->type);
        $this->assertEquals('array', $m->getField('test_array')->type);
        $this->assertEquals('object', $m->getField('test_object')->type);

        // string is default type, so it is null
        $this->assertNull($m->getField('name')->type);
        $this->assertNull($m->getField('test_str_1')->type);
        $this->assertNull($m->getField('test_str_2')->type);
        $this->assertNull($m->getField('test_str_3')->type);
    }
}

class StaticPersistenceModel extends Model
{
    public $title_field = 'foo';

    public function init(): void
    {
        parent::init();

        $this->addField('foo', ['caption'=>'custom field']);
    }
}
