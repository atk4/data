<?php

namespace atk4\data\tests;

use atk4\data\Field;
use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\tests\Model\Client as Client;
use atk4\data\tests\Model\User as User;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class BusinessModelTest extends \atk4\core\PHPUnit_AgileTestCase
{
    /**
     * Test constructor.
     */
    public function testConstructFields()
    {
        $m = new Model();
        $m->addField('name');

        $f = $m->getElement('name');
        $this->assertEquals('name', $f->short_name);

        $m->add(new Field(), 'surname');
        $f = $m->getElement('surname');
        $this->assertEquals('surname', $f->short_name);
    }

    public function testFieldAccess()
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');

        $m['name'] = 5;
        $this->assertEquals(5, $m->get('name'));

        $m->set('surname', 'Bilbo');
        $this->assertEquals(5, $m->get('name'));
        $this->assertEquals('Bilbo', $m->get('surname'));

        $this->assertEquals(['name' => 5, 'surname' => 'Bilbo'], $m->get());
    }

    /**
     * @expectedException Exception
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
        $this->assertEquals(['name' => null], $m->data);
    }

    public function testFieldAccess2()
    {
        $m = new Model(['strict_field_check' => false]);
        $this->assertEquals(false, isset($m['name']));
        $m->set(['name' => 5]);
        $this->assertEquals(true, isset($m['name']));
        $this->assertEquals(5, $m['name']);

        $m['name'] = null;
        $this->assertEquals(false, isset($m['name']));

        $m = new Model();
        $n = $m->addField('name');
        $m->set($n, 5);
        $m->set($n, 5);
        $this->assertEquals(5, $m['name']);
    }

    public function testGet()
    {
        $m = new Model(['strict_field_check' => false]);
        $m->addField('name');
        $m->addField('surname');

        $m->set(['name' => 'john', 'surname' => 'peter', 'foo' => 'bar']);
        $this->assertEquals(['name' => 'john', 'surname' => 'peter'], $m->get());
        $this->assertEquals(['name' => null, 'surname' => null, 'foo' => null], $m->dirty);

        // we can define fields later if strict_field_check=false
        $m->addField('foo');
        $this->assertEquals(['name' => 'john', 'surname' => 'peter', 'foo' => 'bar'], $m->get());
        $this->assertEquals(['name' => null, 'surname' => null, 'foo' => null], $m->dirty);

        // test with onlyFields
        $m->onlyFields(['surname']);
        $this->assertEquals(['surname' => 'peter'], $m->get());
        $this->assertEquals(['name' => null, 'surname' => null, 'foo' => null], $m->dirty);
    }

    public function testDirty()
    {
        $m = new Model();
        $m->addField('name');
        $m->data = ['name' => 5];
        $m['name'] = 5;
        $this->assertEquals([], $m->dirty);

        $m['name'] = 10;
        $this->assertEquals(['name' => 5], $m->dirty);

        $m['name'] = 15;
        $this->assertEquals(['name' => 5], $m->dirty);

        $m['name'] = 5;
        $this->assertEquals([], $m->dirty);

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
        $this->assertEquals([], $m->dirty);

        $m->data = ['name' => '5'];
        $m['name'] = 5;
        $this->assertSame('5', $m->dirty['name']);
        $m['name'] = 6;
        $this->assertSame('5', $m->dirty['name']);
        $m['name'] = 5;
        $this->assertSame('5', $m->dirty['name']);
        $m['name'] = '5';
        $this->assertEquals([], $m->dirty);

        $m->data = ['name' => 4.28];
        $m['name'] = '4.28';
        $this->assertSame(4.28, $m->dirty['name']);
        $m['name'] = '5.28';
        $this->assertSame(4.28, $m->dirty['name']);
        $m['name'] = 4.28;
        $this->assertEquals([], $m->dirty);

        // now with defaults
        $m = new Model();
        $f = $m->addField('name', ['default' => 'John']);
        $this->assertEquals('John', $f->default);

        $this->assertEquals('John', $m->get('name'));

        $m['name'] = null;
        $this->assertEquals(['name' => 'John'], $m->dirty);
        $this->assertEquals(['name' => null], $m->data);
        $this->assertEquals(null, $m['name']);

        unset($m['name']);
        $this->assertEquals('John', $m->get('name'));
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

        $this->assertNotNull($m->getElement('id'));

        $m['id'] = 20;
        $this->assertEquals(20, $m->id);
    }
     */

    /**
     * @expectedException Exception
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
        $this->assertEquals($m['name'], 'foo');

        $m->set(['bar']);
        $this->assertEquals($m['name'], 'bar');

        $m->set(['name' => 'baz']);
        $this->assertEquals($m['name'], 'baz');
    }

    /**
     * @expectedException Exception
     *
     * fields can't be numeric
     */
    public function testException2()
    {
        $m = new Model();
        $m->set(0, 'foo');
    }

    /**
     * @expectedException Exception
     *
     * fields can't be numeric
     */
    public function testException2a()
    {
        $m = new Model();
        $m->set('3', 'foo');
    }

    /**
     * @expectedException Exception
     *
     * fields can't be numeric
     */
    public function testException2b()
    {
        $m = new Model();
        $m->set('3b', 'foo');
    }

    /**
     * @expectedException Exception
     *
     * fields can't be numeric
     */
    public function testException2c()
    {
        $m = new Model();
        $m->set('', 'foo');
    }

    /**
     * @expectedException Exception
     *
     * fields can't be numeric
     */
    public function testException2d()
    {
        $m = new Model();
        $m->set(['foo', 'bar']);
    }

    /**
     * @expectedException Exception
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
        $this->assertSame(null, $m['age']);

        $m['data'] = '';
        $this->assertSame('', $m['data']);
    }

    public function testExampleFromDoc()
    {
        $m = new User();

        $m->addField('salary', ['default' => 1000]);

        $this->assertEquals(false, isset($m['salary']));   // false
        $this->assertEquals(1000, $m['salary']);           // 1000

        // Next we load record from $db
        $m->data = ['salary' => 2000];

        $this->assertEquals(2000, $m['salary']);           // 2000 (from db)
        $this->assertEquals(false, isset($m['salary']));   // false, was not changed

        $m['salary'] = 3000;

        $this->assertEquals(3000, $m['salary']);          // 3000 (changed)
        $this->assertEquals(true, isset($m['salary']));   // true

        unset($m['salary']);        // return to original value

        $this->assertEquals(2000, $m['salary']);          // 2000
        $this->assertEquals(false, isset($m['salary']));  // false
    }
}
