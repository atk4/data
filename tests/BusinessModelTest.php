<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence;
use Atk4\Data\Tests\Model\Client;
use Atk4\Data\Tests\Model\User;

class BusinessModelTest extends TestCase
{
    public function testConstructFields(): void
    {
        $m = new Model();
        $m->addField('name');

        $f = $m->getField('name');
        static::assertSame('name', $f->shortName);

        $m->addField('surname', new Field());
        $f = $m->getField('surname');
        static::assertSame('surname', $f->shortName);
    }

    public function testFieldAccess(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m = $m->createEntity();

        $m->set('name', 5);
        static::assertSame('5', $m->get('name'));

        $m->set('surname', 'Bilbo');
        static::assertSame('5', $m->get('name'));
        static::assertSame('Bilbo', $m->get('surname'));

        static::assertSame(['name' => '5', 'surname' => 'Bilbo'], $m->get());
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
        static::assertSame([], $m->getDirtyRef());

        $m->set('name', 10);
        static::assertSame(['name' => 5], $m->getDirtyRef());

        $m->set('name', 15);
        static::assertSame(['name' => 5], $m->getDirtyRef());

        $m->set('name', 5);
        static::assertSame([], $m->getDirtyRef());

        $m->set('name', '5');
        static::assertSame([], $m->getDirtyRef());

        $m->set('name', '6');
        static::assertSame(['name' => '5'], $m->getDirtyRef());
        $m->set('name', '5');
        static::assertSame([], $m->getDirtyRef());

        $m->set('name', '5.0');
        static::assertSame(['name' => '5'], $m->getDirtyRef());

        $dirtyRef = [];
        $dataRef = ['name' => ''];
        $m->set('name', '');
        static::assertSame([], $m->getDirtyRef());

        $dataRef = ['name' => '5'];
        $m->set('name', 5);
        static::assertSame([], $m->getDirtyRef());
        $m->set('name', 6);
        static::assertSame(['name' => '5'], $m->getDirtyRef());
        $m->set('name', 5);
        static::assertSame([], $m->getDirtyRef());
        $m->set('name', '5');
        static::assertSame([], $m->getDirtyRef());

        $dataRef = ['name' => 4.28];
        $m->set('name', '4.28');
        static::assertSame([], $m->getDirtyRef());
        $m->set('name', '5.28');
        static::assertSame(['name' => 4.28], $m->getDirtyRef());
        $m->set('name', 4.28);
        static::assertSame([], $m->getDirtyRef());

        // now with defaults
        $m = new Model();
        $m->addField('name', ['default' => 'John']);
        $m = $m->createEntity();
        static::assertSame('John', $m->getField('name')->default);

        static::assertSame('John', $m->get('name'));

        $m->set('name', null);
        static::assertSame(['name' => 'John'], $m->getDirtyRef());
        static::assertSame(['name' => null], $m->getDataRef());
        static::assertNull($m->get('name'));

        $m->_unset('name');
        static::assertSame('John', $m->get('name'));
    }

    public function testDefaultInit(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m = $m->createEntity();

        $m->set('id', 20);
        static::assertSame(20, $m->getId());
    }

    public function testException1(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->setOnlyFields(['surname']);
        $m = $m->createEntity();

        $this->expectException(Exception::class);
        $m->set('name', 5);
    }

    public function testException1Fixed(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m->setOnlyFields(['surname']);
        $m = $m->createEntity();

        $m->getModel()->setOnlyFields(null);

        $m->set('name', 5);
        static::assertSame('5', $m->get('name'));
    }

    public function testSetTitle(): void
    {
        $m = new Model();
        $m->addField('name');
        $m = $m->createEntity();
        $m->set('name', 'foo');
        static::assertSame('foo', $m->get('name'));

        $m->set('name', 'baz');
        static::assertSame('baz', $m->get('name'));
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
        static::assertSame(10, $c->get('order'));
    }

    public function testNormalize(): void
    {
        $m = new Model();
        $m->addField('name', ['type' => 'string']);
        $m->addField('age', ['type' => 'integer']);
        $m->addField('data');
        $m = $m->createEntity();

        $m->set('name', '');
        static::assertSame('', $m->get('name'));

        $m->set('age', '');
        static::assertNull($m->get('age'));

        $m->set('data', '');
        static::assertSame('', $m->get('data'));
    }

    public function testExampleFromDoc(): void
    {
        $m = new User();

        $m->addField('salary', ['type' => 'integer', 'default' => 1000]);
        $m = $m->createEntity();

        static::assertSame(1000, $m->get('salary'));
        static::assertFalse($m->_isset('salary'));

        // next we load record from $db
        $dataRef = &$m->getDataRef();
        $dataRef = ['salary' => 2000];
        static::assertSame(2000, $m->get('salary'));
        static::assertFalse($m->_isset('salary'));

        $m->set('salary', 3000);
        static::assertSame(3000, $m->get('salary'));
        static::assertTrue($m->_isset('salary'));

        $m->_unset('salary');
        static::assertSame(2000, $m->get('salary'));
        static::assertFalse($m->_isset('salary'));
    }
}
