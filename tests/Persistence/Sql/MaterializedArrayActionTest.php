<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Data\Persistence\Array_\Action as ArrayAction;
use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\MaterializedArrayAction;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

class MaterializedArrayActionTest extends TestCase
{
    /**
     * @return array{string, array<string, mixed>}
     */
    private function renderQuery(Expressionable $query): array
    {
        $render = $query->getDsqlExpression($this->getConnection()->expr())->render();

        self::assertSame([
            '(' . $render[0] . ')',
            $render[1],
        ], $this->getConnection()->expr('[]', [$query])->render());

        return $render;
    }

    public function testRenderZeroRows(): void
    {
        $action = new ArrayAction([], ['bool', 'int', 'float', 'string']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame([':a' => '[]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` limit 0, 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null, ':c' => null, ':d' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` limit 0 offset 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null, ':c' => null, ':d' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` order by (select null) offset 9223372036854775807 rows fetch next 1 rows only', $render[0]);
            self::assertSame([':a' => null, ':b' => null, ':c' => null, ':d' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` from "DUAL" fetch next 0 rows only', $render[0]);
            self::assertSame([':xxaaaa' => null, ':xxaaab' => null, ':xxaaac' => null, ':xxaaad' => null], $render[1]);
        } else {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` limit 0, 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null, ':c' => null, ':d' => null], $render[1]);
        }

        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }

    public function testRenderOneRow(): void
    {
        $action = new ArrayAction([
            ['bool' => false, 'int' => 0, 'float' => 0.0, 'string' => 'Mark'],
        ], ['bool', 'int', 'float', 'string']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSame([':a' => '[[false,0,0.0,"Mark"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string`', $render[0]);
            self::assertSame([':a' => false, ':b' => 0, ':c' => 0.0, ':d' => 'Mark'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string`', $render[0]);
            self::assertSame([':a' => false, ':b' => 0, ':c' => 0.0, ':d' => 'Mark'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string`', $render[0]);
            self::assertSame([':a' => false, ':b' => 0, ':c' => 0.0, ':d' => 'Mark'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` from "DUAL"', $render[0]);
            self::assertSame([':xxaaaa' => false, ':xxaaab' => 0, ':xxaaac' => 0.0, ':xxaaad' => 'Mark'], $render[1]);
        } else {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string`', $render[0]);
            self::assertSame([':a' => false, ':b' => 0, ':c' => 0.0, ':d' => 'Mark'], $render[1]);
        }

        self::{'assertEquals'}([
            ['bool' => '0', 'int' => 0, 'float' => 0.0, 'string' => 'Mark'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }

    public function testRenderMultipleRows(): void
    {
        $action = new ArrayAction([
            ['bool' => true, 'int' => \PHP_INT_MIN, 'float' => -1e-20, 'string' => ''],
            ['bool' => null, 'int' => \PHP_INT_MAX, 'float' => 1.0123456789123e50, 'string' => ' foo' . "\n"],
        ], ['bool', 'int', 'float', 'string']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('select json_extract(value, \'$[0]\') `bool`, json_extract(value, \'$[1]\') `int`, json_extract(value, \'$[2]\') `float`, json_extract(value, \'$[3]\') `string` from json_each(:a)', $render[0]);
            self::assertSame([':a' => '[[true,-9223372036854775808,-1.0e-20,""],[null,9223372036854775807,1.0123456789123e+50," foo\n"]]'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` union all select :e, :f, :g, :h', $render[0]);
            self::assertSame([':a' => true, ':b' => \PHP_INT_MIN, ':c' => -1e-20, ':d' => '', ':e' => null, ':f' => \PHP_INT_MAX, ':g' => 1.0123456789123e50, ':h' => ' foo' . "\n"], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` union all select :e, :f, :g, :h', $render[0]);
            self::assertSame([':a' => true, ':b' => \PHP_INT_MIN, ':c' => -1e-20, ':d' => '', ':e' => null, ':f' => \PHP_INT_MAX, ':g' => 1.0123456789123e50, ':h' => ' foo' . "\n"], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` union all select :e, :f, :g, :h', $render[0]);
            self::assertSame([':a' => true, ':b' => \PHP_INT_MIN, ':c' => -1e-20, ':d' => '', ':e' => null, ':f' => \PHP_INT_MAX, ':g' => 1.0123456789123e50, ':h' => ' foo' . "\n"], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` from "DUAL" union all select :e, :f, :g, :h from "DUAL"', $render[0]);
            self::assertSame([':xxaaaa' => true, ':xxaaab' => \PHP_INT_MIN, ':xxaaac' => -1e-20, ':xxaaad' => '', ':xxaaae' => null, ':xxaaaf' => \PHP_INT_MAX, ':xxaaag' => 1.0123456789123e50, ':xxaaah' => ' foo' . "\n"], $render[1]);
        } else {
            self::assertSameSql('select :a `bool`, :b `int`, :c `float`, :d `string` union all select :e, :f, :g, :h', $render[0]);
            self::assertSame([':a' => true, ':b' => \PHP_INT_MIN, ':c' => -1e-20, ':d' => '', ':e' => null, ':f' => \PHP_INT_MAX, ':g' => 1.0123456789123e50, ':h' => ' foo' . "\n"], $render[1]);
        }

        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestIncomplete('TODO Oracle remove once JSON_TABLE() is used');
        }

        self::{'assertEquals'}([
            ['bool' => '1', 'int' => \PHP_INT_MIN, 'float' => -1e-20, 'string' => ''],
            ['bool' => null, 'int' => \PHP_INT_MAX, 'float' => 1.0123456789123e50, 'string' => ' foo' . "\n"],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }

    public function testColumnTypeMismatchException(): void
    {
        $action = new ArrayAction([['foo' => 1], ['foo' => '1']], ['foo']);
        $query = new MaterializedArrayAction($action);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Column consists of more than one type');
        $this->renderQuery($query);
    }

    public function testGetRowsUpdatedGenerator(): void
    {
        $action = new ArrayAction([], ['foo', 'bar']);
        $query = new MaterializedArrayAction($action);

        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        $action->generator = new \ArrayIterator([['foo' => 1, 'bar' => 'u']]);
        self::assertSame([
            ['foo' => '1', 'bar' => 'u'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        if ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::markTestIncomplete('TODO Oracle remove once JSON_TABLE() is used');
        }

        $action->generator = new \ArrayIterator([['foo' => 1, 'bar' => 'u'], ['foo' => null, 'bar' => 'v']]);
        self::assertSame([
            ['foo' => '1', 'bar' => 'u'],
            ['foo' => null, 'bar' => 'v'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        $action->generator = new \ArrayIterator([]);
        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }
}
