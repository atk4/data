<?php

declare(strict_types=1);

namespace Atk4\Data\Tests\Persistence\Sql;

use Atk4\Data\Persistence\Array_\Action as ArrayAction;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\MaterializedArrayAction;
use Atk4\Data\Schema\TestCase;

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

        self::assertSameSql('(select :a `a`, :b `bar`)', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            ':a' => null,
            ':b' => null,
        ], $this->renderExpressionable($expr)[1]);

        $action->generator = new \ArrayIterator([['a' => 1, 'bar' => 'u']]);
        self::assertSameSql('(select :a `a`, :b `bar`)', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            ':a' => 1,
            ':b' => 'u',
        ], $this->renderExpressionable($expr)[1]);

        $action->generator = new \ArrayIterator([['a' => 1, 'bar' => 'u'], ['a' => null, 'bar' => 'v']]);
        self::assertSameSql('(select :a `a`, :b `bar` union all select :c, :d)', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            ':a' => 1,
            ':b' => 'u',
            ':c' => null,
            ':d' => 'v',
        ], $this->renderExpressionable($expr)[1]);

        $action->generator = new \ArrayIterator([]);
        self::assertSameSql('(select :a `a`, :b `bar`)', $this->renderExpressionable($expr)[0]);
        self::assertSame([
            ':a' => null,
            ':b' => null,
        ], $this->renderExpressionable($expr)[1]);
    }
}
