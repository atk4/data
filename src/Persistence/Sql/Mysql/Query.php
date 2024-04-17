<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = Expression::QUOTED_TOKEN_REGEX;

    protected string $identifierEscapeChar = '`';
    protected string $expressionClass = Expression::class;

    protected string $templateUpdate = 'update [table][join] set [set] [where]';

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        $serverVersion = $this->connection->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
        if (str_contains($serverVersion, 'MariaDB')) {
            return $sqlLeft . ($negated ? ' not' : '') . ' regexp concat('
                . $this->escapeStringLiteral('(?s)') . ', ' . $sqlRight . ')';
        } elseif (str_starts_with($serverVersion, '5.')) {
            return $sqlLeft . ($negated ? ' not' : '') . ' regexp ' . $sqlRight;
        }

        return ($negated ? 'not ' : '') . 'regexp_like(' . $sqlLeft . ', ' . $sqlRight
            . ', ' . $this->escapeStringLiteral('in') . ')';
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({} separator ' . $this->escapeStringLiteral($separator) . ')', [$field]);
    }
}
