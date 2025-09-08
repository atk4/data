<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Data\Persistence\Array_\Action as ArrayAction;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\MaterializedArrayAction;
use Atk4\Data\Schema\TestCase;
use Doctrine\DBAL\Platforms\OraclePlatform;

class MaterializedArrayActionTest extends TestCase
{
    /**
     * @return array{string, array<string, mixed>}
     */
    protected function renderExpressionable(Expressionable $v): array
    {
        return $this->getConnection()->expr('[]', [$v])->render();
    }

    public function testBasic(): void
    {
        $action = new ArrayAction([], ['a', 'bar']);
        $expr = new MaterializedArrayAction($action);

        $fromClause = $this->getDatabasePlatform() instanceof OraclePlatform
            ? ' from "DUAL"'
            : '';
        $fixParamNameFx = fn ($v) => $this->getDatabasePlatform() instanceof OraclePlatform
            ? ':xxaaa' . substr($v, 1)
            : $v;

        self::assertSameSql('(select :a `a`, :b `bar`' . $fromClause . ')', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            $fixParamNameFx(':a') => null,
            $fixParamNameFx(':b') => null,
        ], $this->renderExpressionable($expr)[1]);

        $action->generator = new \ArrayIterator([['a' => 1, 'bar' => 'u']]);
        self::assertSameSql('(select :a `a`, :b `bar`' . $fromClause . ')', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            $fixParamNameFx(':a') => 1,
            $fixParamNameFx(':b') => 'u',
        ], $this->renderExpressionable($expr)[1]);

        $action->generator = new \ArrayIterator([['a' => 1, 'bar' => 'u'], ['a' => null, 'bar' => 'v']]);
        self::assertSameSql('(select :a `a`, :b `bar`' . $fromClause . ' union all select :c, :d' . $fromClause . ')', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            $fixParamNameFx(':a') => 1,
            $fixParamNameFx(':b') => 'u',
            $fixParamNameFx(':c') => null,
            $fixParamNameFx(':d') => 'v',
        ], $this->renderExpressionable($expr)[1]);

        $action->generator = new \ArrayIterator([]);
        self::assertSameSql('(select :a `a`, :b `bar`' . $fromClause . ')', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            $fixParamNameFx(':a') => null,
            $fixParamNameFx(':b') => null,
        ], $this->renderExpressionable($expr)[1]);
    }
}
