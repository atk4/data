<?php
namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class LimitOrderTest extends SQLTestCase
{

    public function testBasic()
    {
        $a = [
            'invoice'=>[
                ['total_net'=>10],
                ['total_net'=>20],
                ['total_net'=>15],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net','total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);
        $this->assertEquals([
            ['total_net'=>10],
            ['total_net'=>15],
            ['total_net'=>20]
        ],$i->export());
    }

    public function testReverse()
    {
        $a = [
            'invoice'=>[
                ['total_net'=>10, 'total_vat'=>5],
                ['total_net'=>10, 'total_vat'=>4],
                ['total_net'=>15, 'total_vat'=>4],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $ii = (new Model($db, 'invoice'))->addFields(['total_net','total_vat']);
        $ii->addExpression('total_gross', '[total_net]+[total_vat]');

        $i = clone $ii;
        $i->setOrder('total_net desc, total_gross desc');
        $i->onlyFields(['total_net','total_gross']);
        $this->assertEquals([
            ['total_net'=>15, 'total_gross'=>19],
            ['total_net'=>10, 'total_gross'=>15],
            ['total_net'=>10, 'total_gross'=>14],
        ],$i->export());
        $i = clone $ii;
        $i->setOrder('total_net desc, total_gross');
        $i->onlyFields(['total_net','total_gross']);
        $this->assertEquals([
            ['total_net'=>15, 'total_gross'=>19],
            ['total_net'=>10, 'total_gross'=>14],
            ['total_net'=>10, 'total_gross'=>15],
        ],$i->export());

        $i = clone $ii;
        $i->setOrder('total_net desc, total_gross');
        $i->onlyFields(['total_net','total_vat']);
        $this->assertEquals([
            ['total_net'=>15, 'total_vat'=>4],
            ['total_net'=>10, 'total_vat'=>4],
            ['total_net'=>10, 'total_vat'=>5],
        ],$i->export());

        $i = clone $ii;
        $i->setOrder('total_gross desc, total_net');
        $i->onlyFields(['total_net','total_vat']);
        $this->assertEquals([
            ['total_net'=>15, 'total_vat'=>4],
            ['total_net'=>10, 'total_vat'=>5],
            ['total_net'=>10, 'total_vat'=>4],
        ],$i->export());
    }
    public function testLimit()
    {
        $a = [
            'invoice'=>[
                ['total_net'=>10],
                ['total_net'=>20],
                ['total_net'=>15],
            ]];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net','total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $i->setOrder('total_net');
        $i->onlyFields(['total_net']);
        $this->assertEquals([
            ['total_net'=>10],
            ['total_net'=>15],
            ['total_net'=>20]
        ],$i->export());


        $ii=$i;
        $i = clone $ii;
        $i->setLimit(2);
        $this->assertEquals([
            ['total_net'=>10],
            ['total_net'=>15],
        ],$i->export());

        $i = clone $ii;
        $i->setLimit(2,1);
        $this->assertEquals([
            ['total_net'=>15],
            ['total_net'=>20],
        ],$i->export());

        $i = clone $ii;
        $i->setLimit(null,1);
        $this->assertEquals(
            "select `total_net` from `invoice` order by `total_net` limit 1, 9223372036854775807",
            $i->action('select')->render()
        );
        $this->assertEquals([
            ['total_net'=>15],
            ['total_net'=>20],
        ],$i->export());
    }
}
