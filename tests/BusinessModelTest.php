<?php

namespace atk4\data\tests;

use atk4\core\AtkPhpunit;
use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\tests\Model\Client as Client;
use atk4\data\tests\Model\User as User;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class BusinessModelTest extends AtkPhpunit\TestCase
{
    /**
     * Test constructor.
     */
    public function testConstructFields()
    {
        $m = new Model();
        $m->addField('name');

        $f = $m->getField('name');
        $this->assertSame('name', $f->short_name);

        $m->addField('surname', new Field());
        $f = $m->getField('surname');
        $this->assertSame('surname', $f->short_name);
    }

    public function testFieldAccess()
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');

        $m['name'] = 5;
        $this->assertSame(5, $m->get('name'));

        $m->set('surname', 'Bilbo');
        $this->assertSame(5, $m->get('name'));
        $this->assertSame('Bilbo', $m->get('surname'));

        $this->assertSame(['name' => 5, 'surname' => 'Bilbo'], $m->get());
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testNoFieldException()
    {
        $m = new Model();
        $m->set(['name' => 5]);
    }

    public function testNull()
    {
        $m = new Model(['strict_field_check' => false]);
        $m->set(['name' => 5]);
        $m['name'] = null;
        $this->assertSame(['name' => null], $m->data);
    }

    public function testFieldAccess2()
    {
        $m = new Model(['strict_field_check' => false]);
        $this->assertFalse(isset($m['name']));
        $m->set(['name' => 5]);
        $this->assertTrue(isset($m['name']));
        $this->assertSame(5, $m['name']);

        $m['name'] = null;
        $this->assertFalse(isset($m['name']));

        $m = new Model();
        $n = $m->addField('name');
        $m->set($n, 5);
        $m->set($n, 5);
        $this->assertSame(5, $m['name']);
    }

    public function testGet()
    {
        $m = new Model(['strict_field_check' => false]);
        $m->addField('name');
        $m->addField('surname');

        $m->set(['name' => 'john', 'surname' => 'peter', 'foo' => 'bar']);
        $this->assertSame(['name' => 'john', 'surname' => 'peter'], $m->get());
        $this->assertSame(['name' => null, 'surname' => null, 'foo' => null], $m->dirty);

        // we can define fields later if strict_field_check=false
        $m->addField('foo');
        $this->assertSame(['name' => 'john', 'surname' => 'peter', 'foo' => 'bar'], $m->get());
        $this->assertSame(['name' => null, 'surname' => null, 'foo' => null], $m->dirty);

        // test with onlyFields
        $m->onlyFields(['surname']);
        $this->assertSame(['surname' => 'peter'], $m->get());
        $this->assertSame(['name' => null, 'surname' => null, 'foo' => null], $m->dirty);
    }

    public function testDirty()
    {
        $m = new Model();
        $m->addField('name');
        $m->data = ['name' => 5];
        $m['name'] = 5;
        $this->assertSame([], $m->dirty);

        $m['name'] = 10;
        $this->assertSame(['name' => 5], $m->dirty);

        $m['name'] = 15;
        $this->assertSame(['name' => 5], $m->dirty);

        $m['name'] = 5;
        $this->assertSame([], $m->dirty);

        $m['name'] = '5';
        $this->assertSame(5, $m->dirty['name']);

        $m['name'] = '6';
        $this->assertSame(5, $m->dirty['name']);
        $m['name'] = '5';
        $this->assertSame(5, $m->dirty['name']);

        $m['name'] = '5.0';
        $this->assertSame(5, $m->dirty['name']);

        $m->dirty = [];
        $m->data = ['name' => ''];
        $m['name'] = '';
        $this->assertSame([], $m->dirty);

        $m->data = ['name' => '5'];
        $m['name'] = 5;
        $this->assertSame('5', $m->dirty['name']);
        $m['name'] = 6;
        $this->assertSame('5', $m->dirty['name']);
        $m['name'] = 5;
        $this->assertSame('5', $m->dirty['name']);
        $m['name'] = '5';
        $this->assertSame([], $m->dirty);

        $m->data = ['name' => 4.28];
        $m['name'] = '4.28';
        $this->assertSame(4.28, $m->dirty['name']);
        $m['name'] = '5.28';
        $this->assertSame(4.28, $m->dirty['name']);
        $m['name'] = 4.28;
        $this->assertSame([], $m->dirty);

        // now with defaults
        $m = new Model();
        $f = $m->addField('name', ['default' => 'John']);
        $this->assertSame('John', $f->default);

        $this->assertSame('John', $m->get('name'));

        $m['name'] = null;
        $this->assertSame(['name' => 'John'], $m->dirty);
        $this->assertSame(['name' => null], $m->data);
        $this->assertNull($m['name']);

        unset($m['name']);
        $this->assertSame('John', $m->get('name'));
    }

    /*
     * This is no longer the case after PR #69
     *
     * Now changing $m['id'] will actually update the value
     * of original records. In a way $m['id'] is not a direct
     * alias to ID, but has a deeper meaning and behaves more
     * like a regular field.
     *
     *
    public function testDefaultInit()
    {
        $d = new Persistence();
        $m = new Model($d);

        $this->assertNotNull($m->getField('id'));

        $m['id'] = 20;
        $this->assertEquals(20, $m->id);
    }
     */

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testException1()
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->onlyFields(['surname']);

        $m['name'] = 5;
    }

    public function testException1fixed()
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->onlyFields(['surname']);

        $m->allFields();

        $m['name'] = 5;
    }

    /**
     * Sets title field.
     */
    public function testSetTitle()
    {
        $m = new Model();
        $m->addField('name');
        $m->set('foo');
        $this->assertSame($m['name'], 'foo');

        $m->set(['bar']);
        $this->assertSame($m['name'], 'bar');

        $m->set(['name' => 'baz']);
        $this->assertSame($m['name'], 'baz');
    }

    /**
     * @expectedException \atk4\data\Exception
     *
     * fields can't be numeric
     */
    public function testException2()
    {
        $m = new Model();
        $m->set(0, 'foo');
    }

    /**
     * @expectedException \atk4\data\Exception
     *
     * fields can't be numeric
     */
    public function testException2a()
    {
        $m = new Model();
        $m->set('3', 'foo');
    }

    /**
     * @expectedException \atk4\data\Exception
     *
     * fields can't be numeric
     */
    public function testException2b()
    {
        $m = new Model();
        $m->set('3b', 'foo');
    }

    /**
     * @expectedException \atk4\data\Exception
     *
     * fields can't be numeric
     */
    public function testException2c()
    {
        $m = new Model();
        $m->set('', 'foo');
    }

    /**
     * @expectedException \atk4\data\Exception
     *
     * fields can't be numeric
     */
    public function testException2d()
    {
        $m = new Model();
        $m->set(['foo', 'bar']);
    }

    /**
     * @expectedException \atk4\data\Exception
     */
    public function testException3()
    {
        $m = new Model();
        $m->set(4, 5);
    }

    public function testClass1()
    {
        $p = new Persistence();
        $c = new Client($p);
        $this->assertEquals(10, $c['order']);
    }

    public function testNormalize()
    {
        $m = new Model();
        $m->addField('name', ['type' => 'string']);
        $m->addField('age', ['type' => 'int']);
        $m->addField('data');

        $m['name'] = '';
        $this->assertSame('', $m['name']);

        $m['age'] = '';
        $this->assertNull($m['age']);

        $m['data'] = '';
        $this->assertSame('', $m['data']);
    }

    public function testExampleFromDoc()
    {
        $m = new User();

        $m->addField('salary', ['default' => 1000]);

        $this->assertFalse(isset($m['salary']));   // false
        $this->assertSame(1000, $m['salary']);           // 1000

        // Next we load record from $db
        $m->data = ['salary' => 2000];

        $this->assertSame(2000, $m['salary']);           // 2000 (from db)
        $this->assertFalse(isset($m['salary']));   // false, was not changed

        $m['salary'] = 3000;

        $this->assertSame(3000, $m['salary']);          // 3000 (changed)
        $this->assertTrue(isset($m['salary']));   // true

        unset($m['salary']);        // return to original value

        $this->assertSame(2000, $m['salary']);          // 2000
        $this->assertFalse(isset($m['salary']));  // false
    }
}
