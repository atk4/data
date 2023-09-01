<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $identifierEscapeChar = '`';
    protected string $expressionClass = Expression::class;

    protected array $supportedOperators = ['=', '!=', '<', '>', '<=', '>=', 'like', 'not like', 'in', 'not in', 'regexp', 'not regexp'];

    protected string $templateUpdate = 'update [table][join] set [set] [where]';

    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({} separator ' . $this->escapeStringLiteral($separator) . ')', [$field]);
    }
}
