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

        $columnTypes = [];
        foreach ($rows as $row) {
            foreach ($row as $columnName => $v) {
                if ($v === null) {
                    continue;
                } elseif (is_bool($v)) {
                    $type = 'boolean';
                } elseif (is_int($v)) {
                    $type = 'bigint';
                } elseif (is_float($v)) {
                    $type = 'float';
                } else {
                    $type = 'string';
                }

                if (!isset($columnTypes[$columnName])) {
                    $columnTypes[$columnName] = $type;
                } elseif ($type !== $columnTypes[$columnName]) {
                    throw (new Exception('Column consists of more than one type'))
                        ->addMoreInfo('typeA', $columnTypes[$columnName])
                        ->addMoreInfo('typeB', $type);
                }
            }
        }
        $columnTypes = array_merge(array_fill_keys($this->action->getColumns(), 'string'), $columnTypes);

        // TODO add "json" type support, needs ArrayAction optional type support + add test to MaterializedArrayActionTest::testRenderXxxRows

        return \Closure::bind(static fn () => $expression->connection->dsql()->makeArrayTable($rows, $columnTypes), null, Expression::class)();
    }
}
