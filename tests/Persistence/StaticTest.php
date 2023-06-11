<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence;

use Atk4\Core\Phpunit\TestCase;
use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class StaticTest extends TestCase
{
    public function testBasicStatic(): void
    {
        $p = new Persistence\Static_([1 => 'hello', 'world']);

        // default title field
        $m = new Model($p);
        $m = $m->load(2);
        self::assertSame('world', $m->get('name'));

        // custom title field and try loading from same static twice
        $m = new Model($p); // , ['titleField' => 'foo']);
        $m = $m->load(2);
        self::assertSame('world', $m->get('name')); // still 'name' here not 'foo'
    }

    public function testArrayOfArrays(): void
    {
        $p = new Persistence\Static_([1 => ['hello', 'xx', true], ['world', 'xy', false]]);
        $m = new Model($p);

        $m = $m->load(2);

        self::assertSame('world', $m->get('name'));
        self::assertSame('xy', $m->get('field1'));
        self::assertFalse($m->get('field2'));
    }

    public function testArrayOfHashes(): void
    {
        $p = new Persistence\Static_([1 => ['foo' => 'hello'], ['foo' => 'world']]);
        $m = new Model($p);

        $m = $m->load(2);

        self::assertSame('world', $m->get('foo'));
    }

    public function testIdArg(): void
    {
        $p = new Persistence\Static_([
            ['id' => 20, 'foo' => 'hello'],
            ['id' => 21, 'foo' => 'world'],
        ]);
        $m = new Model($p);

        $m = $m->load(21);

        self::assertSame('world', $m->get('foo'));
    }

    public function testIdKey(): void
    {
        $p = new Persistence\Static_([20 => ['foo' => 'hello'], ['foo' => 'world']]);
        $m = new Model($p);

        $m = $m->load(21);

        self::assertSame('world', $m->get('foo'));
    }

    public function testZeroIdAllowed(): void
    {
        $p = new Persistence\Static_(['hello', 'world']);
        $m = new class($p) extends Model {
            protected function init(): void
            {
                parent::init();

                $this->getField('id')->nullable = false;
                $this->getField('id')->required = false;
            }
        };

        self::assertSame('hello', $m->load(0)->get('name'));
        self::assertSame('world', $m->load(1)->get('name'));
    }

    public function testZeroIdNotAllowed(): void
    {
        $p = new Persistence\Static_(['hello', 'world']);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Must not be a zero');
        new Model($p);
    }

    public function testEmpty(): void
    {
        $p = new Persistence\Static_([]);
        $m = new Model($p);

        $m = $m->tryLoadAny();
        self::assertNull($m);
    }

    public function testCustomField(): void
    {
        $p = new Persistence\Static_([1 => ['foo' => 'hello'], ['foo' => 'world']]);
        $m = new StaticTestModel($p);

        self::assertSame('custom field', $m->getField('foo')->caption);

        $p = new Persistence\Static_([1 => ['foo' => 'hello', 'bar' => 'world']]);
        $m = new StaticTestModel($p);
        self::assertSame('foo', $m->titleField);
    }

    public function testTitleOrName(): void
    {
        $p = new Persistence\Static_([1 => ['foo' => 'hello', 'bar' => 'world']]);
        $m = new Model($p);
        self::assertSame('foo', $m->titleField);

        $p = new Persistence\Static_([1 => ['foo' => 'hello', 'name' => 'x']]);
        $m = new Model($p);
        self::assertSame('name', $m->titleField);

        $p = new Persistence\Static_([1 => ['foo' => 'hello', 'title' => 'x']]);
        $m = new Model($p);
        self::assertSame('title', $m->titleField);
    }

    public function testFieldTypes(): void
    {
        $p = new Persistence\Static_([1 => [
            'name' => 'hello',
            'test_int' => 123,
            'test_float' => 123.45,
            // 'test_date' => new \DateTime(),
            // 'test_array' => ['a', 'b', 'c'],
            // 'test_object' => new \DateInterval('P1Y'),
            'test_str_1' => 'abc',
            'test_str_2' => '123',
            'test_str_3' => '123.45',
        ]]);
        $m = new Model($p);

        self::assertSame('integer', $m->getField('id')->type);
        self::assertSame('integer', $m->getField('test_int')->type);
        self::assertSame('float', $m->getField('test_float')->type);
        // self::assertSame('datetime', $m->getField('test_date')->type);
        // self::assertSame('json', $m->getField('test_array')->type);
        // self::assertSame('object', $m->getField('test_object')->type);

        // string is default type
        self::assertSame('string', $m->getField('name')->type);
        self::assertSame('string', $m->getField('test_str_1')->type);
        self::assertSame('string', $m->getField('test_str_2')->type);
        self::assertSame('string', $m->getField('test_str_3')->type);
    }

    public function testFieldTypesBasicInteger(): void
    {
        $p = new Persistence\Static_([1 => 'hello', 'world']);
        $m = new Model($p);

        self::assertSame('integer', $m->getField('id')->type);
        self::assertSame('string', $m->getField('name')->type);
    }

    public function testFieldTypesBasicString(): void
    {
        $p = new Persistence\Static_(['test' => 'hello', 10 => 'world']);
        $m = new Model($p);

        self::assertSame('string', $m->getField('id')->type);
        self::assertSame('string', $m->getField('name')->type);
    }

    public function testFieldTypesIntegerNullFirst(): void
    {
        $p = new Persistence\Static_([1 => ['foo' => null], 2 => ['foo' => 1], 3 => ['foo' => null]]);
        $m = new Model($p);

        self::assertSame('integer', $m->getField('id')->type);
        self::assertSame('integer', $m->getField('foo')->type);
    }

    public function testFieldTypesBasicIntegerNullFirst(): void
    {
        $p = new Persistence\Static_([1 => null, 2 => 1, 3 => null]);
        $m = new Model($p);

        self::assertSame('integer', $m->getField('id')->type);
        self::assertSame('integer', $m->getField('name')->type);
    }
}

class StaticTestModel extends Model
{
    public ?string $titleField = 'foo';

    protected function init(): void
    {
        parent::init();

        $this->addField('foo', ['caption' => 'custom field']);
    }
}
