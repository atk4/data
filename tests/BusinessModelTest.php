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
        self::assertSame('name', $f->shortName);

        $m->addField('surname', new Field());
        $f = $m->getField('surname');
        self::assertSame('surname', $f->shortName);
    }

    public function testFieldAccess(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('surname');
        $m = $m->createEntity();

        $m->set('name', 5);
        self::assertSame('5', $m->get('name'));

        $m->set('surname', 'Bilbo');
        self::assertSame('5', $m->get('name'));
        self::assertSame('Bilbo', $m->get('surname'));

        self::assertSame(['name' => '5', 'surname' => 'Bilbo'], $m->get());
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
        self::assertSame([], $m->getDirtyRef());

        $m->set('name', 10);
        self::assertSame(['name' => 5], $m->getDirtyRef());

        $m->set('name', 15);
        self::assertSame(['name' => 5], $m->getDirtyRef());

        $m->set('name', 5);
        self::assertSame([], $m->getDirtyRef());

        $m->set('name', '5');
        self::assertSame([], $m->getDirtyRef());

        $m->set('name', '6');
        self::assertSame(['name' => '5'], $m->getDirtyRef());
        $m->set('name', '5');
        self::assertSame([], $m->getDirtyRef());

        $m->set('name', '5.0');
        self::assertSame(['name' => '5'], $m->getDirtyRef());

        $dirtyRef = [];
        $dataRef = ['name' => ''];
        $m->set('name', '');
        self::assertSame([], $m->getDirtyRef());

        $dataRef = ['name' => '5'];
        $m->set('name', 5);
        self::assertSame([], $m->getDirtyRef());
        $m->set('name', 6);
        self::assertSame(['name' => '5'], $m->getDirtyRef());
        $m->set('name', 5);
        self::assertSame([], $m->getDirtyRef());
        $m->set('name', '5');
        self::assertSame([], $m->getDirtyRef());

        $dataRef = ['name' => 4.28];
        $m->set('name', '4.28');
        self::assertSame([], $m->getDirtyRef());
        $m->set('name', '5.28');
        self::assertSame(['name' => 4.28], $m->getDirtyRef());
        $m->set('name', 4.28);
        self::assertSame([], $m->getDirtyRef());

        // now with defaults
        $m = new Model();
        $m->addField('name', ['default' => 'John']);
        $m = $m->createEntity();
        self::assertSame('John', $m->getField('name')->default);

        self::assertSame('John', $m->get('name'));

        $m->set('name', null);
        self::assertSame(['name' => 'John'], $m->getDirtyRef());
        self::assertSame(['name' => null], $m->getDataRef());
        self::assertNull($m->get('name'));

        $m->_unset('name');
        self::assertSame('John', $m->get('name'));
    }

    public function testDefaultInit(): void
    {
        $p = new Persistence\Array_();
        $m = new Model($p);
        $m = $m->createEntity();

        $m->set('id', 20);
        self::assertSame(20, $m->getId());
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
        self::assertSame('5', $m->get('name'));
    }

    public function testSetTitle(): void
    {
        $m = new Model();
        $m->addField('name');
        $m = $m->createEntity();
        $m->set('name', 'foo');
        self::assertSame('foo', $m->get('name'));

        $m->set('name', 'baz');
        self::assertSame('baz', $m->get('name'));
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
        self::assertSame(10, $c->get('order'));
    }

    public function testNormalize(): void
    {
        $m = new Model();
        $m->addField('name', ['type' => 'string']);
        $m->addField('age', ['type' => 'integer']);
        $m->addField('data');
        $m = $m->createEntity();

        $m->set('name', '');
        self::assertSame('', $m->get('name'));

        $m->set('age', '');
        self::assertNull($m->get('age'));

        $m->set('data', '');
        self::assertSame('', $m->get('data'));
    }

    public function testExampleFromDoc(): void
    {
        $m = new User();

        $m->addField('salary', ['type' => 'integer', 'default' => 1000]);
        $m = $m->createEntity();

        self::assertSame(1000, $m->get('salary'));
        self::assertFalse($m->_isset('salary'));

        // next we load record from $db
        $dataRef = &$m->getDataRef();
        $dataRef = ['salary' => 2000];
        self::assertSame(2000, $m->get('salary'));
        self::assertFalse($m->_isset('salary'));

        $m->set('salary', 3000);
        self::assertSame(3000, $m->get('salary'));
        self::assertTrue($m->_isset('salary'));

        $m->_unset('salary');
        self::assertSame(2000, $m->get('salary'));
        self::assertFalse($m->_isset('salary'));
    }
}
