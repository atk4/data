<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class IteratorTest extends \atk4\schema\PHPUnit_SchemaTestCase
{
    /**
     * If first argument is array, then second argument should not be used.
     *
     * @expectedException Exception
     */
    public function testException1()
    {
        $m = new Model();
        $m->addFields(['name', 'salary']);
        $m->setOrder(['name', 'salary'], 'desc');
    }

    /**
     * Model is not associated with any database - persistence should be set.
     *
     * @expectedException Exception
     */
    public function testException2()
    {
        $m = new Model();
        $m->tryLoad(1);
    }

    /**
     * Model is not associated with any database - persistence should be set.
     *
     * @expectedException Exception
     */
    public function testException3()
    {
        $m = new Model();
        $m->tryLoadAny();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     *
     * @expectedException Exception
     */
    public function testException4()
    {
        $m = new Model();
        $m->load(1);
    }

    /**
     * Model is not associated with any database - persistence should be set.
     *
     * @expectedException Exception
     */
    public function testException5()
    {
        $m = new Model();
        $m->loadAny();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     *
     * @expectedException Exception
     */
    public function testException6()
    {
        $m = new Model();
        $m->save();
    }

    /**
     * Model is not associated with any database - persistence should be set.
     *
     * @expectedException Exception
     */
    public function testException7()
    {
        $m = new Model();
        $m->action('insert');
    }

    public function testBasic()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
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

    public function testRawIterator()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
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

    public function testBasicID()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);

        $data = [];
        foreach ($i as $id => $item) {
            $data[$id] = clone $item;
        }

        $this->assertEquals(10, $data[1]['total_net']);
        $this->assertEquals(20, $data[2]['total_net']);
        $this->assertEquals(15, $data[3]['total_net']);
        $this->assertEquals(null, $i['total_net']);
    }
}
