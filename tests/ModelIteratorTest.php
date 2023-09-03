<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Model\Scope;
use Atk4\Data\Schema\TestCase;

class ModelIteratorTest extends TestCase
{
    public function testSetOrderArrayWithAnotherArgumentException(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('salary');

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('If first argument is array, second argument must not be used');
        $m->setOrder(['name', 'salary'], 'desc');
    }

    public function testNoPersistenceTryLoadException(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model is not associated with a persistence');
        $m->tryLoad(1);
    }

    public function testNoPersistenceLoadException(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model is not associated with a persistence');
        $m->load(1);
    }

    public function testNoPersistenceTryLoadAnyException(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model is not associated with a persistence');
        $m->tryLoadAny();
    }

    public function testNoPersistenceSaveException(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model is not associated with a persistence');
        $m->createEntity()->save();
    }

    public function testNoPersistenceActionException(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Model is not associated with a persistence');
        $m->action('count');
    }

    public function testBasic(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'integer']);
        $i->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]', 'type' => 'integer']);

        $i->setOrder('total_net');
        $i->setOnlyFields(['total_net']);

        $data = [];
        foreach ($i as $row) {
            $data[] = $row->get();
        }

        foreach ($i as $row) {
            $data[] = $row->get();
            $i->setLimit(1);
        }

        foreach ($i as $row) {
            $data[] = $row->get();
        }

        self::assertSame([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],

            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],

            ['total_net' => 10], // affected by limit now
        ], $data);
    }

    public function testBasicId(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'integer']);
        $i->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]', 'type' => 'integer']);

        $i->setOrder('total_net');
        $i->setOnlyFields(['total_net']);

        $data = [];
        foreach ($i as $id => $item) {
            $data[$id] = $item;
        }

        self::assertSame(10, $data[1]->get('total_net'));
        self::assertSame(20, $data[2]->get('total_net'));
        self::assertSame(15, $data[3]->get('total_net'));
        self::assertNull($i->createEntity()->get('total_net'));
    }

    public function testCreateIteratorBy(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);

        $i->setOrder('total_net');
        $i->setOnlyFields(['total_net']);

        self::assertSame([
            1 => ['total_net' => 10],
            3 => ['total_net' => 15],
            2 => ['total_net' => 20],
        ], array_map(static fn (Model $m) => $m->get(), iterator_to_array($i->createIteratorBy([]))));

        self::assertSame([
            2 => ['total_net' => 20],
        ], array_map(static fn (Model $m) => $m->get(), iterator_to_array($i->createIteratorBy('total_net', 20))));

        self::assertSame([
        ], array_map(static fn (Model $m) => $m->get(), iterator_to_array($i->createIteratorBy('total_net', '<', 10))));

        self::assertSame([
            1 => ['total_net' => 10],
            3 => ['total_net' => 15],
        ], array_map(static fn (Model $m) => $m->get(), iterator_to_array($i->createIteratorBy('total_net', '!=', 20))));

        self::assertSame([
            1 => ['total_net' => 10],
            2 => ['total_net' => 20],
        ], array_map(static fn (Model $m) => $m->get(), iterator_to_array($i->createIteratorBy(Scope::createOr(['id', 1], ['total_net', 20])))));

        self::assertSame([
            3 => ['total_net' => 15],
        ], array_map(static fn (Model $m) => $m->get(), iterator_to_array($i->createIteratorBy([['total_net', '>', 10], ['total_net', '<', 20]]))));
    }

    public function testCreateIteratorByOneLevelArrayException(): void
    {
        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);

        if (\PHP_MAJOR_VERSION === 7) {
            $this->expectWarning(); // @phpstan-ignore-line
            $this->expectWarningMessage('Only arrays and Traversables can be unpacked'); // @phpstan-ignore-line
        } else {
            $this->expectException(\TypeError::class);
            $this->expectExceptionMessage('Only arrays and Traversables can be unpacked');
        }
        iterator_to_array($i->createIteratorBy(['total_net', 10])); // @phpstan-ignore-line
    }

    public function testCreateIteratorByAssociativeArrayException(): void
    {
        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage(\PHP_MAJOR_VERSION === 7 ? 'Cannot unpack array with string keys' : 'Unknown named parameter $total_net');
        iterator_to_array($i->createIteratorBy([['total_net' => 10]])); // @phpstan-ignore-line
    }
}
