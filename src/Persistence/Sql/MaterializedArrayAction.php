<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Core\WarnDynamicPropertyTrait;
use Atk4\Data\Persistence\Array_\Action as ArrayAction;

class MaterializedArrayAction implements Expressionable
{
    use WarnDynamicPropertyTrait;

    protected ArrayAction $action;

    public function __construct(ArrayAction $action)
    {
        $this->action = $action;
    }

    #[\Override]
    public function getDsqlExpression(Expression $expression): Expression
    {
        $rows = $this->action->getRows();

        if ($rows === []) {
            $query = $expression->connection->dsql();
            foreach ($this->action->getColumns() as $k) {
                $query->field($query->expr('[]', [null]), $k);
            }

            return $query;
        }

        // TODO simplify once https://github.com/atk4/data/pull/677 is merged
        $queries = [];
        $isFirst = true;
        foreach ($rows as $row) {
            $query = $expression->connection->dsql();
            $query->wrapInParentheses = false;
            foreach ($row as $k => $v) {
                $query->field($query->expr('[]', [$v]), $isFirst ? $k : null);
            }

            $queries[] = $query;
            $isFirst = false;
        }

        return $expression->expr([
            'template' => implode(' union all ', array_map(static fn () => '[]', $queries)),
            'wrapInParentheses' => true,
        ], $queries);
    }
}
