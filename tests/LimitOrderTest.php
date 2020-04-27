<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class LimitOrderTest extends \atk4\schema\PhpunitTestCase
{
    public function testBasic()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ], ];
        $this->setDB($a);

        $i = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');
        $i->getField($i->id_field)->system = false;

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);
        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());
    }

    public function testReverse()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 5], // total_gross 15
                ['total_net' => 10, 'total_vat' => 4], // total_gross 14
                ['total_net' => 15, 'total_vat' => 4], // total_gross 19
            ], ];
        $this->setDB($a);

        $ii = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $ii->addExpression('total_gross', '[total_net]+[total_vat]');
        $ii->getField($ii->id_field)->system = false;

        // pass parameters as CSV string
        $i = clone $ii;
        $i->setOrder('total_net desc, total_gross desc');
        $i->onlyFields(['total_net', 'total_gross']);
        $this->assertEquals([
            ['total_net' => 15, 'total_gross' => 19],
            ['total_net' => 10, 'total_gross' => 15],
            ['total_net' => 10, 'total_gross' => 14],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder('total_net desc, total_gross');
        $i->onlyFields(['total_net', 'total_gross']);
        $this->assertEquals([
            ['total_net' => 15, 'total_gross' => 19],
            ['total_net' => 10, 'total_gross' => 14],
            ['total_net' => 10, 'total_gross' => 15],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder('total_net desc, total_gross');
        $i->onlyFields(['total_net', 'total_vat']);
        $this->assertEquals([
            ['total_net' => 15, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 5],
        ], $i->export());

        $i = clone $ii;
        $i->setOrder('total_gross desc, total_net');
        $i->onlyFields(['total_net', 'total_vat']);
        $this->assertEquals([
            ['total_net' => 15, 'total_vat' => 4],
            ['total_net' => 10, 'total_vat' => 5],
            ['total_net' => 10, 'total_vat' => 4],
        ], $i->export());
    }

    public function testArrayParameters()
    {
        $a = [
            'invoice' => [
                ['net' => 10, 'vat' => 5],
                ['net' => 10, 'vat' => 4],
                ['net' => 15, 'vat' => 4],
            ], ];
        $this->setDB($a);

        $ii = (new Model($this->db, 'invoice'))->addFields(['net', 'vat']);
        $ii->getField($ii->id_field)->system = false;

        // pass parameters as array elements [field,order]
        $i = clone $ii;
        $i->setOrder([['net', 'desc'], ['vat']]);
        $i->onlyFields(['net', 'vat']);
        $this->assertEquals([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());

        // pass parameters as array elements [field=>order]
        $i = clone $ii;
        $i->setOrder(['net' => true, 'vat' => false]);
        $i->onlyFields(['net', 'vat']);
        $this->assertEquals([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());

        // pass parameters as array elements [field=>order], same as above but use 'desc' instead of true
        $i = clone $ii;
        $i->setOrder(['net' => 'desc', 'vat']); // and you can even mix them (see 'vat' is a value not a key here)
        $i->onlyFields(['net', 'vat']);
        $this->assertEquals([
            ['net' => 15, 'vat' => 4],
            ['net' => 10, 'vat' => 4],
            ['net' => 10, 'vat' => 5],
        ], $i->export());
    }

    public function testOrderByExpressions()
    {
        $a = [
            'invoice' => [
                ['code' => 'A', 'net' => 10, 'vat' => 5],
                ['code' => 'B', 'net' => 10, 'vat' => 4],
                ['code' => 'C', 'net' => 15, 'vat' => 4],
            ], ];
        $this->setDB($a);

        // order by expression field
        $i = (new Model($this->db, 'invoice'))->addFields(['code', 'net', 'vat']);
        $i->addExpression('gross', '[net]+[vat]');
        $i->getField($i->id_field)->system = false;

        $i->setOrder('gross');
        $i->onlyFields(['gross']);
        $this->assertEquals([
            ['gross' => 14],
            ['gross' => 15],
            ['gross' => 19],
        ], $i->export());

        // order by expression not defined as separate expression field in model
        $i->order = []; // reset
        $i->setOrder($i->expr('[net]*[vat]'));
        $i->onlyFields(['code']);
        $this->assertEquals([
            ['code' => 'B'], // 10 * 4 = 40
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'C'], // 15 * 4 = 60
        ], $i->export());

        // "desc" as part of expression string
        $i->order = []; // reset
        $i->setOrder($i->expr('[net]*[vat] desc'));
        $i->onlyFields(['code']);
        $this->assertEquals([
            ['code' => 'C'], // 15 * 4 = 60
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'B'], // 10 * 4 = 40
        ], $i->export());

        // "desc" as 2nd parameter
        $i->order = []; // reset
        $i->setOrder($i->expr('[net]*[vat]'), 'desc');
        $i->onlyFields(['code']);
        $this->assertEquals([
            ['code' => 'C'], // 15 * 4 = 60
            ['code' => 'A'], // 10 * 5 = 50
            ['code' => 'B'], // 10 * 4 = 40
        ], $i->export());

        // order by mixed array of expressions and field names
        $i->order = []; // reset
        $i->setOrder(['vat', $i->expr('[net]*[vat]')]);
        $i->onlyFields(['code']);
        $this->assertEquals([
            ['code' => 'B'], // 4, 10 * 4 = 40
            ['code' => 'C'], // 4, 15 * 4 = 60
            ['code' => 'A'], // 5, 10 * 5 = 50
        ], $i->export());
    }

    /**
     * Unsupported order parameter.
     *
     * @expectedException Exception
     */
    public function testExceptionUnsupportedOrderParam()
    {
        $a = [
            'invoice' => [
                ['net' => 10],
            ], ];
        $this->setDB($a);

        $i = (new Model($this->db, 'invoice'))->addFields(['net']);
        $i->setOrder(new \DateTime());
        $i->export(); // executes query and throws exception because of DateTime object
    }

    public function testLimit()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10],
                ['total_net' => 20],
                ['total_net' => 15],
            ], ];
        $this->setDB($a);

        $i = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');
        $i->getField($i->id_field)->system = false;

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);
        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());

        $ii = $i;
        $i = clone $ii;
        $i->setLimit(2);
        $this->assertEquals([
            ['total_net' => 10],
            ['total_net' => 15],
        ], $i->export());

        $i = clone $ii;
        $i->setLimit(2, 1);
        $this->assertEquals([
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());

        $i = clone $ii;
        $i->setLimit(null, 1);
        /*
        This test is incorrect because last number in rendered query is dependent on server.
        For example, on Imants Win10 64-bit this renders as:
        select "total_net" from "invoice" order by "total_net" limit 1, 2147483647
        On Travis server it renders as:
        select "total_net" from "invoice" order by "total_net" limit 1, 9223372036854775807
        which still is not equal to max number which SQL server allows - 18446744073709551615
        $this->assertEquals(
            'select "total_net" from "invoice" order by "total_net" limit 1, 9223372036854775807',
            $i->action('select')->render()
        );
        */
        $this->assertEquals([
            ['total_net' => 15],
            ['total_net' => 20],
        ], $i->export());
    }
}
