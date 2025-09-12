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
        $action = new ArrayAction([], ['a', 'bar']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('select :a `a`, :b `bar` limit 0, 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` limit 0, 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` limit 0 offset 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` order by (select null) offset 9223372036854775807 rows fetch next 1 rows only', $render[0]);
            self::assertSame([':a' => null, ':b' => null], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `a`, :b `bar` from "DUAL" fetch next 0 rows only', $render[0]);
            self::assertSame([':xxaaaa' => null, ':xxaaab' => null], $render[1]);
        } else {
            self::assertSameSql('select :a `a`, :b `bar` limit 0, 0', $render[0]);
            self::assertSame([':a' => null, ':b' => null], $render[1]);
        }
    }

    public function testRenderOneRow(): void
    {
        $action = new ArrayAction([['a' => 1, 'bar' => 'u']], ['a', 'bar']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('select :a `a`, :b `bar`', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar`', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar`', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `a`, :b `bar`', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `a`, :b `bar` from "DUAL"', $render[0]);
            self::assertSame([':xxaaaa' => 1, ':xxaaab' => 'u'], $render[1]);
        } else {
            self::assertSameSql('select :a `a`, :b `bar`', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u'], $render[1]);
        }
    }

    public function testRenderMultipleRows(): void
    {
        $action = new ArrayAction([['a' => 1, 'bar' => 'u'], ['a' => null, 'bar' => 'v']], ['a', 'bar']);
        $query = new MaterializedArrayAction($action);

        $render = $this->renderQuery($query);
        if ($this->getDatabasePlatform() instanceof SQLitePlatform) {
            self::assertSameSql('select :a `a`, :b `bar` union all select :c, :d', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u', ':c' => null, ':d' => 'v'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof MySQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` union all select :c, :d', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u', ':c' => null, ':d' => 'v'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` union all select :c, :d', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u', ':c' => null, ':d' => 'v'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` union all select :c, :d', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u', ':c' => null, ':d' => 'v'], $render[1]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `a`, :b `bar` from "DUAL" union all select :c, :d from "DUAL"', $render[0]);
            self::assertSame([':xxaaaa' => 1, ':xxaaab' => 'u', ':xxaaac' => null, ':xxaaad' => 'v'], $render[1]);
        } else {
            self::assertSameSql('select :a `a`, :b `bar` union all select :c, :d', $render[0]);
            self::assertSame([':a' => 1, ':b' => 'u', ':c' => null, ':d' => 'v'], $render[1]);
        }
    }

    public function testColumnTypeMismatchException(): void
    {
        $action = new ArrayAction([['a' => 1], ['a' => '1']], ['a']);
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
