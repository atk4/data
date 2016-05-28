<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;
use atk4\data\Field;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class BusinessModelTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Test constructor
     *
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

        $this->assertEquals(['name'=>5, 'surname'=>'Bilbo'], $m->get());
    }

    public function testNull()
    {
        $m = new Model();
        $m->set(['name'=>5]);
        $m['name'] = null;
        $this->assertEquals(['name'=>null], $m->data);
    }

    public function testFieldAccess2()
    {
        $m = new Model();
        $this->assertEquals(false, isset($m['name']));
        $m->set(['name'=>5]);
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
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->set(['name'=>'john', 'surname'=>'peter', 'foo'=>'bar']);
        $this->assertEquals(['name'=>'john', 'surname'=>'peter'], $m->get());

        $m->onlyFields(['surname']);
        $this->assertEquals(['surname'=>'peter'], $m->get());
    }

    public function testDirty()
    {
        $m = new Model();
        $m->data = ['name'=>5];
        $m['name'] = 10;
        $this->assertEquals(['name'=>5], $m->dirty);

        $m['name'] = 5;
        $this->assertEquals([], $m->dirty);

        // now with defaults
        $m = new Model();
        $f = $m->addField('name', ['default'=>'John']);
        $this->assertEquals('John', $f->default);

        $this->assertEquals('John', $m->get('name'));

        $m['name'] = null;
        $this->assertEquals(['name'=>'John'], $m->dirty);
        $this->assertEquals(['name'=>null], $m->data);
        $this->assertEquals(null, $m['name']);

        unset($m['name']);
        $this->assertEquals('John', $m->get('name'));
    }

    public function testDefaultInit()
    {
        $d = new Persistence();
        $m = new Model($d);

        $this->assertNotNull($m->getElement('id'));

        $m['id'] = 20;
        $this->assertEquals(20, $m->id);
    }

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
     * @expectedException Exception
     */
    public function testException2()
    {
        $m = new Model();
        $m->set('foo');

    }

    /**
     * @expectedException Exception
     */
    public function testException3()
    {
        $m = new Model();
        $m->set(4, 5);
    }
}
