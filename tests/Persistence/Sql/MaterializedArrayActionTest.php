<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Data\Persistence\Array_\Action as ArrayAction;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\MaterializedArrayAction;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
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

    public function testBasic(): void
    {
        $action = new ArrayAction([], ['a', 'bar']);
        $query = new MaterializedArrayAction($action);

        $fromClause = $this->getDatabasePlatform() instanceof OraclePlatform
            ? ' from "DUAL"'
            : '';
        $fixParamNameFx = fn ($v) => $this->getDatabasePlatform() instanceof OraclePlatform
            ? ':xxaaa' . substr($v, 1)
            : $v;

        if ($this->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` limit 0 offset 0', $this->renderQuery($query)[0]);
        } elseif ($this->getDatabasePlatform() instanceof SQLServerPlatform) {
            self::assertSameSql('select :a `a`, :b `bar` order by (select null) offset 9223372036854775807 rows fetch next 1 rows only', $this->renderQuery($query)[0]);
        } elseif ($this->getDatabasePlatform() instanceof OraclePlatform) {
            self::assertSameSql('select :a `a`, :b `bar` from "DUAL" fetch next 0 rows only', $this->renderQuery($query)[0]);
        } else {
            self::assertSameSql('select :a `a`, :b `bar` limit 0, 0', $this->renderQuery($query)[0]);
        }
        self::assertSame([
            $fixParamNameFx(':a') => null,
            $fixParamNameFx(':b') => null,
        ], $this->renderQuery($query)[1]);

        $action->generator = new \ArrayIterator([['a' => 1, 'bar' => 'u']]);
        self::assertSameSql('select :a `a`, :b `bar`' . $fromClause, $this->renderQuery($query)[0]);
        self::assertSame([
            $fixParamNameFx(':a') => 1,
            $fixParamNameFx(':b') => 'u',
        ], $this->renderQuery($query)[1]);

        $action->generator = new \ArrayIterator([['a' => 1, 'bar' => 'u'], ['a' => null, 'bar' => 'v']]);
        self::assertSameSql('select :a `a`, :b `bar`' . $fromClause . ' union all select :c, :d' . $fromClause, $this->renderQuery($query)[0]);
        self::assertSame([
            $fixParamNameFx(':a') => 1,
            $fixParamNameFx(':b') => 'u',
            $fixParamNameFx(':c') => null,
            $fixParamNameFx(':d') => 'v',
        ], $this->renderQuery($query)[1]);
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

        $action->generator = new \ArrayIterator([['foo' => 1, 'bar' => 'u'], ['foo' => null, 'bar' => 'v']]);
        self::assertSame([
            ['foo' => '1', 'bar' => 'u'],
            ['foo' => null, 'bar' => 'v'],
        ], $query->getDsqlExpression($this->getConnection()->expr())->getRows());

        $action->generator = new \ArrayIterator([]);
        self::assertSame([], $query->getDsqlExpression($this->getConnection()->expr())->getRows());
    }
}
