<?php

declare(strict_types=1);

namespace atk4\data\tests;

use atk4\data\Model;
use atk4\data\Persistence;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

/**
 * @coversDefaultClass \atk4\data\Model
 */
class ExpressionSqlTest extends \atk4\schema\PhpunitTestCase
{
    public function testNakedExpression()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, false);
        $m->addExpression('x', '2+3');
        $m->tryLoadAny();
        $this->assertEquals(5, $m->get('x'));
    }

    public function testBasic()
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', '[total_net]+[total_vat]');

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id","total_net","total_vat",("total_net"+"total_vat") "total_gross" from "invoice"',
                $i->toQuery()->select()->render()
            );
        }

        $ii = (clone $i)->tryLoad(1);
        $this->assertEquals(10, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $ii = (clone $i)->tryLoad(2);
        $this->assertEquals(20, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $i->addExpression('double_total_gross', '[total_gross]*2');

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertEquals(
                'select "id","total_net","total_vat",("total_net"+"total_vat") "total_gross",(("total_net"+"total_vat")*2) "double_total_gross" from "invoice"',
                $i->toQuery()->select()->render()
            );
        }

        $i->tryLoad(1);
        $this->assertEquals(($i->get('total_net') + $i->get('total_vat')) * 2, $i->get('double_total_gross'));
    }

    public function testBasicCallback()
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', function ($i, $q) {
            return '[total_net]+[total_vat]';
        });

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id","total_net","total_vat",("total_net"+"total_vat") "total_gross" from "invoice"',
                $i->toQuery()->select()->render()
            );
        }

        $ii = (clone $i)->tryLoad(1);
        $this->assertEquals(10, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $ii = (clone $i)->tryLoad(2);
        $this->assertEquals(20, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));
    }

    public function testQuery()
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $i = (new Model($db, 'invoice'))->addFields(['total_net', 'total_vat']);
        $i->addExpression('sum_net', $i->toQuery()->aggregate('sum', 'total_net'));

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id","total_net","total_vat",(select sum("total_net") from "invoice") "sum_net" from "invoice"',
                $i->toQuery()->select()->render()
            );
        }

        $i->tryLoad(1);
        $this->assertEquals(10, $i->get('total_net'));
        $this->assertEquals(30, $i->get('sum_net'));

        $q = $db->dsql();
        $q->field($i->toQuery()->count(), 'total_orders');
        $q->field($i->toQuery()->aggregate('sum', 'total_net'), 'total_net');
        $this->assertEquals(
            ['total_orders' => 2, 'total_net' => 30],
            $q->getRow()
        );
    }

    public function testExpressions()
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'cached_name' => 'John Smith'],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'cached_name' => 'ERROR'],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'user');
        $m->addFields(['name', 'surname', 'cached_name']);

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $m->addExpression('full_name', '[name] || " " || [surname]');
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $m->addExpression('full_name', '[name] || \' \' || [surname]');
        } else {
            $m->addExpression('full_name', 'CONCAT([name], \' \', [surname])');
        }

        $m->addCondition($m->expr('[full_name] != [cached_name]'));

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id","name","surname","cached_name",("name" || " " || "surname") "full_name" from "user" where (("name" || " " || "surname") != "cached_name")',
                $m->toQuery()->select()->render()
            );
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $this->assertSame(
                'select "id","name","surname","cached_name",("name" || \' \' || "surname") "full_name" from "user" where (("name" || \' \' || "surname") != "cached_name")',
                $m->toQuery()->select()->render()
            );
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            $this->assertSame(
                'select `id`,`name`,`surname`,`cached_name`,(CONCAT(`name`, \' \', `surname`)) `full_name` from `user` where ((CONCAT(`name`, \' \', `surname`)) != `cached_name`)',
                $m->toQuery()->select()->render()
            );
        }

        $m->tryLoad(1);
        $this->assertNull($m->get('name'));
        $m->tryLoad(2);
        $this->assertSame('Sue', $m->get('name'));
    }

    public function testReloading()
    {
        $this->setDb($dbData = [
            'math' => [
                ['a' => 2, 'b' => 2],
            ],
        ]);

        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, 'math');
        $m->addFields(['a', 'b']);

        $m->addExpression('sum', '[a] + [b]');

        $mm = (clone $m)->load(1);
        $this->assertEquals(4, $mm->get('sum'));

        $mm->save(['a' => 3]);
        $this->assertEquals(5, $mm->get('sum'));

        $this->assertEquals(9, $m->unload()->save(['a' => 4, 'b' => 5])->get('sum'));

        $this->setDb($dbData);
        $m = new Model($db, ['math', 'reload_after_save' => false]);
        $m->addFields(['a', 'b']);

        $m->addExpression('sum', '[a] + [b]');

        $mm = (clone $m)->load(1);
        $this->assertEquals(4, $mm->get('sum'));

        $mm->save(['a' => 3]);
        $this->assertEquals(4, $mm->get('sum'));

        $this->assertNull($m->unload()->save(['a' => 4, 'b' => 5])->get('sum'));
    }

    public function testExpressionActionAlias()
    {
        $db = new Persistence\Sql($this->db->connection);
        $m = new Model($db, false);
        $m->addExpression('x', '2+3');

        // use alias as array key if it is set
        $q = $m->toQuery()->field('x', 'foo');
        $this->assertEquals([0 => ['foo' => 5]], $q->get());

        // if alias is not set, then use field name as key
        $q = $m->toQuery()->field('x');
        $this->assertEquals([0 => ['x' => 5]], $q->get());

        // FX actions
        $q = $m->toQuery()->aggregate('sum', 'x', 'foo');
        $this->assertEquals([0 => ['foo' => 5]], $q->get());

        $q = $m->toQuery()->aggregate('sum', 'x');
        $this->assertEquals([0 => ['sum_x' => 5]], $q->get());

        $q = $m->toQuery()->aggregate('sum', 'x', 'foo', true);
        $this->assertEquals([0 => ['foo' => 5]], $q->get());

        $q = $m->toQuery()->aggregate('sum', 'x', null, true);
        $this->assertEquals([0 => ['sum_x' => 5]], $q->get());
    }
}
