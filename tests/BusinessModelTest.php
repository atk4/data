<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\AtkPhpunit;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Tests\Model\Client;
use Atk4\Data\Tests\Model\User;

class BusinessModelTest extends AtkPhpunit\TestCase
{
    /**
     * Test constructor.
     */
    public function testConstructFields(): void
    {
        $m = new Model();
        $m->addField('name');

        $f = $m->getField('name');
        $this->assertSame('name', $f->short_name);

        $m->addField('surname', new Field());
        $f = $m->getField('surname');
        $this->assertSame('surname', $f->short_name);
    }

    public function testFieldAccess(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m = $m->createEntity();

        $m->set('name', 5);
        $this->assertSame(5, $m->get('name'));

        $m->set('surname', 'Bilbo');
        $this->assertSame(5, $m->get('name'));
        $this->assertSame('Bilbo', $m->get('surname'));

        $this->assertSame(['name' => 5, 'surname' => 'Bilbo'], $m->get());
    }

    public function testNoFieldException(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('name', 5);
    }

    public function testDirty(): void
    {
        $m = new Model();
        $m->addField('name');
        $m = $m->createEntity();
        $dataRef = &$m->getDataRef();
        $dirtyRef = &$m->getDirtyRef();
        $dataRef = ['name' => 5];
        $m->set('name', 5);
        $this->assertSame([], $m->getDirtyRef());

        $m->set('name', 10);
        $this->assertSame(['name' => 5], $m->getDirtyRef());

        $m->set('name', 15);
        $this->assertSame(['name' => 5], $m->getDirtyRef());

        $m->set('name', 5);
        $this->assertSame([], $m->getDirtyRef());

        $m->set('name', '5');
        $this->assertSame([], $m->getDirtyRef());

        $m->set('name', '6');
        $this->assertSame(['name' => 5], $m->getDirtyRef());
        $m->set('name', '5');
        $this->assertSame([], $m->getDirtyRef());

        $m->set('name', '5.0');
        $this->assertSame(['name' => '5'], $m->getDirtyRef());

        $dirtyRef = [];
        $dataRef = ['name' => ''];
        $m->set('name', '');
        $this->assertSame([], $m->getDirtyRef());

        $dataRef = ['name' => '5'];
        $m->set('name', 5);
        $this->assertSame([], $m->getDirtyRef());
        $m->set('name', 6);
        $this->assertSame(['name' => '5'], $m->getDirtyRef());
        $m->set('name', 5);
        $this->assertSame([], $m->getDirtyRef());
        $m->set('name', '5');
        $this->assertSame([], $m->getDirtyRef());

        $dataRef = ['name' => 4.28];
        $m->set('name', '4.28');
        $this->assertSame([], $m->getDirtyRef());
        $m->set('name', '5.28');
        $this->assertSame(['name' => 4.28], $m->getDirtyRef());
        $m->set('name', 4.28);
        $this->assertSame([], $m->getDirtyRef());

        // now with defaults
        $m = new Model();
        $f = $m->addField('name', ['default' => 'John']);
        $m = $m->createEntity();
        $this->assertSame('John', $f->default);

        $this->assertSame('John', $m->get('name'));

        $m->set('name', null);
        $this->assertSame(['name' => 'John'], $m->getDirtyRef());
        $this->assertSame(['name' => null], $m->getDataRef());
        $this->assertNull($m->get('name'));

        $m->_unset('name');
        $this->assertSame('John', $m->get('name'));
    }

    public function testDefaultInit(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m = $m->createEntity();

        $this->assertNotNull($m->getField('id'));

        $m->set('id', 20);
        $this->assertEquals(20, $m->getId());
    }

    public function testException1(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->onlyFields(['surname']);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('name', 5);
    }

    public function testException1Fixed(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->onlyFields(['surname']);
        $m = $m->createEntity();

        $m->allFields();

        $m->set('name', 5);
        $this->assertSame(5, $m->get('name'));
    }

    /**
     * Sets title field.
     */
    public function testSetTitle(): void
    {
        $m = new Model();
        $m->addField('name');
        $m = $m->createEntity();
        $m->set('name', 'foo');
        $this->assertSame('foo', $m->get('name'));

        $m->set('name', 'baz');
        $this->assertSame('baz', $m->get('name'));
    }

    /**
     * Fields can't be numeric.
     */
    public function testException2(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(\Error::class);
        $m->set(0, 'foo'); // @phpstan-ignore-line
    }

    public function testException2a(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('3', 'foo');
    }

    public function testException2b(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('3b', 'foo');
    }

    public function testException2c(): void
    {
        $m = new Model();
        $m = $m->createEntity();
        $this->expectException(Exception::class);
        $m->set('', 'foo');
    }

    public function testClass1(): void
    {
        $p = new Persistence\Array_();
        $c = new Client($p);
        $c = $c->createEntity();
        $this->assertEquals(10, $c->get('order'));
    }

    public function testNormalize(): void
    {
        $m = new Model();
        $m->addField('name', ['type' => 'string']);
        $m->addField('age', ['type' => 'int']);
        $m->addField('data');
        $m = $m->createEntity();

        $m->set('name', '');
        $this->assertSame('', $m->get('name'));

        $m->set('age', '');
        $this->assertNull($m->get('age'));

        $m->set('data', '');
        $this->assertSame('', $m->get('data'));
    }

    public function testExampleFromDoc(): void
    {
        $m = new User();

        $m->addField('salary', ['default' => 1000]);
        $m = $m->createEntity();

        $this->assertSame(1000, $m->get('salary'));
        $this->assertFalse($m->_isset('salary'));

        // Next we load record from $db
        $dataRef = &$m->getDataRef();
        $dataRef = ['salary' => 2000];
        $this->assertSame(2000, $m->get('salary'));
        $this->assertFalse($m->_isset('salary'));

        $m->set('salary', 3000);
        $this->assertSame(3000, $m->get('salary'));
        $this->assertTrue($m->_isset('salary'));

        $m->_unset('salary');
        $this->assertSame(2000, $m->get('salary'));
        $this->assertFalse($m->_isset('salary'));
    }
}
