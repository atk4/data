<?php

declare(strict_types=1);

namespace Atk4\Data\Tests;

use Atk4\Data\Model;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;

class ExpressionSqlTest extends TestCase
{
    public function testNakedExpression(): void
    {
        $m = new Model($this->db, ['table' => false]);
        $m->addExpression('x', ['expr' => '2 + 3', 'type' => 'integer']);
        $m = $m->loadOne();
        self::assertSame(5, $m->get('x'));
    }

    public function testBasic(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'float']);
        $i->addExpression('total_gross', ['expr' => '[total_net] + [total_vat]', 'type' => 'float']);

        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame(
                'select `id`, `total_net`, `total_vat`, (`total_net` + `total_vat`) `total_gross` from `invoice`',
                $i->action('select')->render()[0]
            );
        }

        $ii = $i->load(1);
        self::assertSame(10, $ii->get('total_net'));
        self::assertSame($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $ii = $i->load(2);
        self::assertSame(20, $ii->get('total_net'));
        self::assertSame($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $i->addExpression('double_total_gross', ['expr' => '[total_gross] * 2', 'type' => 'float']);

        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame(
                'select `id`, `total_net`, `total_vat`, (`total_net` + `total_vat`) `total_gross`, ((`total_net` + `total_vat`) * 2) `double_total_gross` from `invoice`',
                $i->action('select')->render()[0]
            );
        }

        $i = $i->load(1);
        self::assertSame(($i->get('total_net') + $i->get('total_vat')) * 2, $i->get('double_total_gross'));
    }

    public function testBasicCallback(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'float']);
        $i->addExpression('total_gross', ['expr' => static function () {
            return '[total_net] + [total_vat]';
        }, 'type' => 'float']);

        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame(
                'select `id`, `total_net`, `total_vat`, (`total_net` + `total_vat`) `total_gross` from `invoice`',
                $i->action('select')->render()[0]
            );
        }

        $ii = $i->load(1);
        self::assertSame(10, $ii->get('total_net'));
        self::assertSame($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));

        $ii = $i->load(2);
        self::assertSame(20, $ii->get('total_net'));
        self::assertSame($ii->get('total_net') + $ii->get('total_vat'), $ii->get('total_gross'));
    }

    public function testQuery(): void
    {
        $this->setDb([
            'invoice' => [
                ['total_net' => 10, 'total_vat' => 1.23],
                ['total_net' => 20, 'total_vat' => 2.46],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);
        $i->addField('total_net', ['type' => 'integer']);
        $i->addField('total_vat', ['type' => 'float']);
        $i->addExpression('sum_net', ['expr' => $i->action('fx', ['sum', 'total_net']), ['type' => 'integer']]);

        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame(
                'select `id`, `total_net`, `total_vat`, (select sum(`total_net`) from `invoice`) `sum_net` from `invoice`',
                $i->action('select')->render()[0]
            );
        }

        $ii = $i->load(1);
        self::assertSame(10, $ii->get('total_net'));
        self::assertSame(30, $ii->get('sum_net'));

        $q = $this->db->dsql();
        $q->field($i->action('count'), 'total_orders');
        $q->field($i->action('fx', ['sum', 'total_net']), 'total_net');
        self::assertSame(
            ['total_orders' => '2', 'total_net' => '30'],
            $q->getRow()
        );
    }

    public function testExpressions(): void
    {
        $this->setDb([
            'user' => [
                1 => ['id' => 1, 'name' => 'John', 'surname' => 'Smith', 'cached_name' => 'John Smith'],
                ['id' => 2, 'name' => 'Sue', 'surname' => 'Sue', 'cached_name' => 'ERROR'],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'user']);
        $m->addField('name');
        $m->addField('surname');
        $m->addField('cached_name');

        if ($this->getDatabasePlatform() instanceof SQLitePlatform || $this->getDatabasePlatform() instanceof OraclePlatform) {
            $concatExpr = '[name] || \' \' || [surname]';
        } else {
            $concatExpr = 'CONCAT([name], \' \', [surname])';
        }
        $m->addExpression('full_name', ['expr' => $concatExpr]);

        $m->addCondition($m->expr('[full_name] != [cached_name]'));

        $concatSql = preg_replace('~\[(\w+)\]~', '`$1`', $concatExpr);
        $this->assertSameSql(
            'select `id`, `name`, `surname`, `cached_name`, (' . $concatSql . ') `full_name` from `user` where ((' . $concatSql . ') != `cached_name`)',
            $m->action('select')->render()[0]
        );

        $mm = $m->tryLoad(1);
        self::assertNull($mm);
        $mm = $m->load(2);
        self::assertSame('Sue', $mm->get('name'));
    }

    public function testReloading(): void
    {
        $this->setDb($dbData = [
            'math' => [
                ['a' => 2, 'b' => 2],
            ],
        ]);

        $m = new Model($this->db, ['table' => 'math']);
        $m->addField('a', ['type' => 'integer']);
        $m->addField('b', ['type' => 'integer']);

        $m->addExpression('sum', ['expr' => '[a] + [b]', 'type' => 'integer']);

        $mm = $m->load(1);
        self::assertSame(4, $mm->get('sum'));

        $mm->save(['a' => 3]);
        self::assertSame(5, $mm->get('sum'));

        self::assertSame(9, $m->createEntity()->save(['a' => 4, 'b' => 5])->get('sum'));

        $this->dropCreatedDb();
        $this->setDb($dbData);

        $m = new Model($this->db, ['table' => 'math', 'reloadAfterSave' => false]);
        $m->addField('a', ['type' => 'integer']);
        $m->addField('b', ['type' => 'integer']);

        $m->addExpression('sum', ['expr' => '[a] + [b]', 'type' => 'integer']);

        $mm = $m->load(1);
        self::assertSame(4, $mm->get('sum'));

        $mm->save(['a' => 3]);
        self::assertSame(4, $mm->get('sum'));

        self::assertNull($m->createEntity()->save(['a' => 4, 'b' => 5])->get('sum'));
    }

    public function testExpressionActionAlias(): void
    {
        $m = new Model($this->db, ['table' => false]);
        $m->addExpression('x', ['expr' => '2 + 3', 'type' => 'integer']);

        // use alias as array key if it is set
        $q = $m->action('field', ['x', 'alias' => 'foo']);
        self::assertSame([['foo' => '5']], $q->getRows());

        // if alias is not set, then use field name as key
        $q = $m->action('field', ['x']);
        self::assertSame([['x' => '5']], $q->getRows());

        // FX actions
        $q = $m->action('fx', ['sum', 'x', 'alias' => 'foo']);
        self::assertSame([['foo' => '5']], $q->getRows());

        $q = $m->action('fx', ['sum', 'x']);
        self::assertSame([['sum_x' => '5']], $q->getRows());

        $q = $m->action('fx0', ['sum', 'x', 'alias' => 'foo']);
        self::assertSame([['foo' => '5']], $q->getRows());

        $q = $m->action('fx0', ['sum', 'x']);
        self::assertSame([['sum_x' => '5']], $q->getRows());
    }

    public function testNeverSaveNeverPersist(): void
    {
        $this->setDb([
            'invoice' => [
                ['foo' => 'bar'],
            ],
        ]);

        $i = new Model($this->db, ['table' => 'invoice']);

        $i->addExpression('zero_basic', ['expr' => $i->expr('0'), 'type' => 'integer']);
        $i->addExpression('zero_neverSave', ['expr' => $i->expr('0'), 'type' => 'integer', 'neverSave' => true]);
        $i->addExpression('zero_neverPersist', ['expr' => $i->expr('0'), 'type' => 'integer', 'neverPersist' => true]);
        $i->addExpression('one_basic', ['expr' => $i->expr('1'), 'type' => 'integer', 'system' => true]);
        $i->addExpression('one_neverSave', ['expr' => $i->expr('1'), 'type' => 'integer', 'neverSave' => true, 'system' => true]);
        $i->addExpression('one_neverPersist', ['expr' => $i->expr('1'), 'type' => 'integer', 'neverPersist' => true, 'system' => true]);
        $i = $i->loadOne();

        // normal fields
        self::assertSame(0, $i->get('zero_basic'));
        self::assertSame(1, $i->get('one_basic'));

        // neverSave - are loaded from DB, but not saved
        self::assertSame(0, $i->get('zero_neverSave'));
        self::assertSame(1, $i->get('one_neverSave'));

        // neverPersist - are not loaded from DB and not saved - as result expressions will not be executed
        self::assertNull($i->get('zero_neverPersist'));
        self::assertNull($i->get('one_neverPersist'));
    }
}
