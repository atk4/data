<?php

namespace atk4\data\tests;

use atk4\data\Model;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class LimitOrderTest extends \atk4\schema\PHPUnit_SchemaTestCase
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
        $i->getElement('id')->system = false;

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
                ['total_net' => 10, 'total_vat' => 5],
                ['total_net' => 10, 'total_vat' => 4],
                ['total_net' => 15, 'total_vat' => 4],
            ], ];
        $this->setDB($a);

        $ii = (new Model($this->db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $ii->addExpression('total_gross', '[total_net]+[total_vat]');
        $ii->getElement('id')->system = false;

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
        $i->getElement('id')->system = false;

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
        This test is incorrect because last number in rendered query is dependant on server.
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
