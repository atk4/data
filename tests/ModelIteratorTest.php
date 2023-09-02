<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;

class ModelIteratorTest extends TestCase
{
    /**
     * If first argument is array, then second argument should not be used.
     */
    public function testException1(): void
    {
        $m = new Model();
        $m->addField('name');
        $m->addField('salary');
        $this->expectException(Exception::class);
        $m->setOrder(['name', 'salary'], 'desc');
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException2(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $m->tryLoad(1);
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException3(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $m->tryLoadAny();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException4(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $m->load(1);
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException5(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
        $m->loadAny();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException6(): void
    {
        $m = new Model();

        $this->expectException(\TypeError::class);
        $this->expectExceptionMessage('Expected entity, but instance is a model');
        $m->save();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException7(): void
    {
        $m = new Model();

        $this->expectException(Exception::class);
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
}
