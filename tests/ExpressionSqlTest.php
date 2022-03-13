<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SqlitePlatform;

class ExpressionSqlTest extends TestCase
{
    public function testNakedExpression(): void
    {
        $m = new Model($this->db, ['table' => false]);
        $m->addExpression('x', ['expr' => '2 + 3', 'type' => 'integer']);
        $m = $m->loadOne();
        $this->assertSame(5, $m->get('x'));
    }

    public function testBasic(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]']);

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id", "total_net", "total_vat", ("total_net" + "total_vat") "total_gross" from "invoice"',
                $i->action('select')->render()[0]
            );
        }

        $ii = $i->tryLoad(1);
        $this->assertEquals(10, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $ii = $i->tryLoad(2);
        $this->assertEquals(20, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $i->addExpression('double_total_gross', ['expr' => '[total_gross] * 2']);

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertEquals(
                'select "id", "total_net", "total_vat", ("total_net" + "total_vat") "total_gross", (("total_net" + "total_vat") * 2) "double_total_gross" from "invoice"',
                $i->action('select')->render()[0]
            );
        }

        $i = $i->tryLoad(1);
        $this->assertEquals(($i->get('total_net') + $i->get('total_vat')) * 2, $i->get('double_total_gross'));
    }

    public function testBasicCallback(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['total_net', 'total_vat']);
        $i->addExpression('total_gross', ['expr' => function ($i, $q) {
            return '[total_net] + [total_vat]';
        }, 'type' => 'float']);

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id", "total_net", "total_vat", ("total_net" + "total_vat") "total_gross" from "invoice"',
                $i->action('select')->render()[0]
            );
        }

        $ii = $i->tryLoad(1);
        $this->assertEquals(10, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $ii = $i->tryLoad(2);
        $this->assertEquals(20, $ii->get('total_net'));
        $this->assertEquals($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));
    }

    public function testQuery(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $i = (new Model($this->db, ['table' => 'invoice']))->addFields(['total_net', 'total_vat']);
        $i->addExpression('sum_net', ['expr' => $i->action('fx', ['sum', 'total_net'])]);

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $this->assertSame(
                'select "id", "total_net", "total_vat", (select sum("total_net") from "invoice") "sum_net" from "invoice"',
                $i->action('select')->render()[0]
            );
        }

        $ii = $i->tryLoad(1);
        $this->assertEquals(10, $ii->get('total_net'));
        $this->assertEquals(30, $ii->get('sum_net'));

        $q = $this->db->dsql();
        $q->field($i->action('count'), 'total_orders');
        $q->field($i->action('fx', ['sum', 'total_net']), 'total_net');
        $this->assertEquals(
            ['total_orders' => 2, 'total_net' => 30],
            $q->getRow()
        );
    }

    public function testExpressions(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'cached_name' => 'John Smith'],
                2 => ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'cached_name' => 'ERROR'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addFields(['name', 'surname', 'cached_name']);

        if ($this->getDatabasePlatform() instanceof SqlitePlatform) {
            $concatExpr = '[name] || " " || [surname]';
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            $concatExpr = '[name] || \' \' || [surname]';
        } else {
            $concatExpr = 'CONCAT([name], \' \', [surname])';
        }
        $m->addExpression('full_name', ['expr' => $concatExpr]);

        $m->addCondition($m->expr('[full_name] != [cached_name]'));

        $concatSql = preg_replace('~\[(\w+)\]~', '"$1"', $concatExpr);
        $this->assertSameSql(
            'select "id", "name", "surname", "cached_name", (' . $concatSql . ') "full_name" from "user" where ((' . $concatSql . ') != "cached_name")',
            $m->action('select')->render()[0]
        );

        $mm = $m->tryLoad(1);
        $this->assertNull($mm->get('name'));
        $mm = $m->tryLoad(2);
        $this->assertSame('Sue', $mm->get('name'));
    }

    public function testReloading(): void
    {
        $this->setDb($dbData = [
            'math' => [
                ['a' => 2, 'b' => 2],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'math']);
        $m->addFields(['a', 'b']);

        $m->addExpression('sum', ['expr' => '[a] + [b]']);

        $mm = $m->load(1);
        $this->assertEquals(4, $mm->get('sum'));

        $mm->save(['a' => 3]);
        $this->assertEquals(5, $mm->get('sum'));

        $this->assertEquals(9, $m->createEntity()->save(['a' => 4, 'b' => 5])->get('sum'));

        $this->setDb($dbData);
        $m = new Model($this->db, ['table' => 'math', 'reload_after_save' => false]);
        $m->addFields(['a', 'b']);

        $m->addExpression('sum', ['expr' => '[a] + [b]']);

        $mm = $m->load(1);
        $this->assertEquals(4, $mm->get('sum'));

        $mm->save(['a' => 3]);
        $this->assertEquals(4, $mm->get('sum'));

        $this->assertNull($m->createEntity()->save(['a' => 4, 'b' => 5])->get('sum'));
    }

    public function testExpressionActionAlias(): void
    {
        $m = new Model($this->db, ['table' => false]);
        $m->addExpression('x', ['expr' => '2 + 3']);

        // use alias as array key if it is set
        $q = $m->action('field', ['x', 'alias' => 'foo']);
        $this->assertEquals([['foo' => 5]], $q->getRows());

        // if alias is not set, then use field name as key
        $q = $m->action('field', ['x']);
        $this->assertEquals([['x' => 5]], $q->getRows());

        // FX actions
        $q = $m->action('fx', ['sum', 'x', 'alias' => 'foo']);
        $this->assertEquals([['foo' => 5]], $q->getRows());

        $q = $m->action('fx', ['sum', 'x']);
        $this->assertEquals([['sum_x' => 5]], $q->getRows());

        $q = $m->action('fx0', ['sum', 'x', 'alias' => 'foo']);
        $this->assertEquals([['foo' => 5]], $q->getRows());

        $q = $m->action('fx0', ['sum', 'x']);
        $this->assertEquals([['sum_x' => 5]], $q->getRows());
    }

    public function testNeverSaveNeverPersist(): void
    {
        $this->setDb([
            'invoice' => [
                ['foo' => 'bar'],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);

        $i->addExpression('zero_basic', ['expr' => $i->expr('0'), 'type' => 'integer', 'system' => true]);
        $i->addExpression('zero_never_save', ['expr' => $i->expr('0'), 'type' => 'integer', 'system' => true, 'never_save' => true]);
        $i->addExpression('zero_never_persist', ['expr' => $i->expr('0'), 'type' => 'integer', 'system' => true, 'never_persist' => true]);
        $i->addExpression('one_basic', ['expr' => $i->expr('1'), 'type' => 'integer', 'system' => true]);
        $i->addExpression('one_never_save', ['expr' => $i->expr('1'), 'type' => 'integer', 'system' => true, 'never_save' => true]);
        $i->addExpression('one_never_persist', ['expr' => $i->expr('1'), 'type' => 'integer', 'system' => true, 'never_persist' => true]);
        $i = $i->loadOne();

        // normal fields
        $this->assertSame(0, $i->get('zero_basic'));
        $this->assertSame(1, $i->get('one_basic'));

        // never_save - are loaded from DB, but not saved
        $this->assertSame(0, $i->get('zero_never_save'));
        $this->assertSame(1, $i->get('one_never_save'));

        // never_persist - are not loaded from DB and not saved - as result expressions will not be executed
        $this->assertNull($i->get('zero_never_persist'));
        $this->assertNull($i->get('one_never_persist'));
    }
}
