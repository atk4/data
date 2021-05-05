<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Exception;
use Atk4\Data\Model;
use Atk4\Data\Persistence;

class IteratorTest extends \Atk4\Schema\PhpunitTestCase
{
    /**
     * If first argument is array, then second argument should not be used.
     */
    public function testException1(): void
    {
        $m = new Model();
        $m->addFields(['name', 'salary']);
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
        $m = $m->tryLoad(1);
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException3(): void
    {
        $m = new Model();
        $this->expectException(Exception::class);
        $m = $m->tryLoadAny();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException4(): void
    {
        $m = new Model();
        $this->expectException(Exception::class);
        $m = $m->load(1);
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException5(): void
    {
        $m = new Model();
        $this->expectException(Exception::class);
        $m = $m->loadAny();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException6(): void
    {
        $m = new Model();
        $this->expectException(Exception::class);
        $m->save();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     */
    public function testException7(): void
    {
        $m = new Model();
        $this->expectException(Exception::class);
        $m->action('insert');
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

        $db = new Persistence\Sql($this->db->connection);
        $i = (new Model($db, ['table' => 'invoice']))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);

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

        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],

            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],

            ['total_net' => 10], // affected by limit now
        ], $data);
    }

    public function testRawIterator(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $i = (new Model($db, ['table' => 'invoice']))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);

        $data = [];
        foreach ($i->rawIterator() as $row) {
            $data[] = $row;

            break;
        }

        foreach ($i->rawIterator() as $row) {
            $data[] = $row;
            $i->setLimit(1);
        }

        foreach ($i->rawIterator() as $row) {
            $data[] = $row;
        }

        $this->assertEquals([
            ['total_net' => 10, 'id' => 1],

            ['total_net' => 10, 'id' => 1],
            ['total_net' => 15, 'id' => 3],
            ['total_net' => 20, 'id' => 2],

            ['total_net' => 10, 'id' => 1], // affected by limit now
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

        $db = new Persistence\Sql($this->db->connection);
        $i = (new Model($db, ['table' => 'invoice']))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);

        $data = [];
        foreach ($i as $id => $item) {
            $data[$id] = $item;
        }

        $this->assertEquals(10, $data[1]->get('total_net'));
        $this->assertEquals(20, $data[2]->get('total_net'));
        $this->assertEquals(15, $data[3]->get('total_net'));
        $this->assertNull($i->createEntity()->get('total_net'));
    }
}
