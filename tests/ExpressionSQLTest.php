<?php

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence_SQL;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ExpressionSQLTest extends SQLTestCase
{
    public function testNakedExpression()
    {
        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, false);
        $m->addExpression('x', '2+3');
        $m->tryLoadAny();
        $this->assertEquals(5, $m['x']);
    }

    public function testBasic()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        $this->assertEquals(
            'select `id`,`total_net`,`total_vat`,(`total_net`+`total_vat`) `total_gross` from `invoice`',
            $i->action('select')->render()
        );

        $i->tryLoad(1);
        $this->assertEquals(10, $i['total_net']);
        $this->assertEquals($i['total_net'] + $i['total_vat'], $i['total_gross']);

        $i->tryLoad(2);
        $this->assertEquals(20, $i['total_net']);
        $this->assertEquals($i['total_net'] + $i['total_vat'], $i['total_gross']);

        $i->addExpression('double_total_gross', '[total_gross]*2');

        $this->assertEquals(
            'select `id`,`total_net`,`total_vat`,(`total_net`+`total_vat`) `total_gross`,((`total_net`+`total_vat`)*2) `double_total_gross` from `invoice`',
            $i->action('select')->render()
        );

        $i->tryLoad(1);
        $this->assertEquals(($i['total_net'] + $i['total_vat']) * 2, $i['double_total_gross']);
    }

    public function testBasicCallback()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', function ($i, $q) {
            return '[total_net]+[total_vat]';
        });

        $this->assertEquals(
            'select `id`,`total_net`,`total_vat`,(`total_net`+`total_vat`) `total_gross` from `invoice`',
            $i->action('select')->render()
        );

        $i->tryLoad(1);
        $this->assertEquals(10, $i['total_net']);
        $this->assertEquals($i['total_net'] + $i['total_vat'], $i['total_gross']);

        $i->tryLoad(2);
        $this->assertEquals(20, $i['total_net']);
        $this->assertEquals($i['total_net'] + $i['total_vat'], $i['total_gross']);
    }

    public function testQuery()
    {
        $a = [
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('sum_net', $i->action('fx', ['sum', 'total_net']));

        $this->assertEquals(
            'select `id`,`total_net`,`total_vat`,(select sum(`total_net`) from `invoice`) `sum_net` from `invoice`',
            $i->action('select')->render()
        );

        $i->tryLoad(1);
        $this->assertEquals(10, $i['total_net']);
        $this->assertEquals(30, $i['sum_net']);

        $q = $db->dsql();
        $q->field($i->action('count'), 'total_orders');
        $q->field($i->action('fx', ['sum', 'total_net']), 'total_net');
        $this->assertEquals(
            ['total_orders' => 2, 'total_net' => 30],
            $q->getRow()
        );
    }

    public function testExpressions()
    {
        $a = [
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'cached_name' => 'John Smith'],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'cached_name' => 'ERROR'],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name', 'surname', 'cached_name']);

        $m->addExpression('full_name', '[name] || " " || [surname]');
        $m->addCondition($m->expr('[full_name] != [cached_name]'));

        $this->assertEquals(
            'select `id`,`name`,`surname`,`cached_name`,(`name` || " " || `surname`) `full_name` from `user` where (`name` || " " || `surname`) != `cached_name`',
            $m->action('select')->render()
        );

        $m->tryLoad(1);
        $this->assertEquals(null, $m['name']);
        $m->tryLoad(2);
        $this->assertEquals('Sue', $m['name']);
    }

    public function testReloading()
    {
        $a = [
            'math' => [
                ['a' => 2, 'b' => 2],
            ], ];
        $this->setDB($a);

        $db = new Persistence_SQL($this->db->connection);
        $m = new Model($db, 'math');
        $m->addFields(['a', 'b']);

        $m->addExpression('sum', '[a] + [b]');

        $m->load(1);
        $this->assertEquals(4, $m['sum']);

        $m->save(['a' => 3]);
        $this->assertEquals(5, $m['sum']);

        $this->assertEquals(9, $m->unload()->save(['a' => 4, 'b' => 5])->get('sum'));

        $this->setDB($a);
        $m = new Model($db, ['math', 'reload_after_save' => false]);
        $m->addFields(['a', 'b']);

        $m->addExpression('sum', '[a] + [b]');

        $m->load(1);
        $this->assertEquals(4, $m['sum']);

        $m->save(['a' => 3]);
        $this->assertEquals(4, $m['sum']);

        $this->assertEquals(null, $m->unload()->save(['a' => 4, 'b' => 5])->get('sum'));
    }
}
