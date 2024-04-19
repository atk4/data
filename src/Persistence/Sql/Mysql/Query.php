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
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        $serverVersion = $this->connection->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
        $isMysql5x = str_starts_with($serverVersion, '5.') && !str_contains($serverVersion, 'MariaDB');

        if ($isMysql5x) {
            $replaceSqlFx = function (string $sql, string $search, string $replacement) {
                return 'replace(' . $sql . ', ' . $this->escapeStringLiteral($search) . ', ' . $this->escapeStringLiteral($replacement) . ')';
            };

            // workaround missing regexp_replace() function
            // https://devblogs.microsoft.com/azure-sql/introducing-regular-expression-regex-support-in-azure-sql-db/
            $sqlRightEscaped = $sqlRight;
            foreach (['\\', '_', '%'] as $v) {
                $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '\\' . $v, '\\' . "\x01" . $v);
            }
            $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '\\', '\\\\');
            foreach (['\\', '_', '%'] as $v) {
                $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '\\\\' . "\x01" . str_replace('\\', '\\\\', $v), '\\' . $v);
            }
        } else {
            $sqlRightEscaped = 'regexp_replace(' . $sqlRight . ', '
                . $this->escapeStringLiteral('\\\\\\\|\\\(?![_%])') . ', '
                . $this->escapeStringLiteral('\\\\\\\\') . ')';
        }

        return $sqlLeft . ($negated ? ' not' : '') . ' like ' . $sqlRightEscaped
            . ' escape ' . $this->escapeStringLiteral('\\');
    }

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        $serverVersion = $this->connection->getConnection()->getWrappedConnection()->getServerVersion(); // @phpstan-ignore-line
        $isMysql5x = str_starts_with($serverVersion, '5.') && !str_contains($serverVersion, 'MariaDB');

        return $sqlLeft . ($negated ? ' not' : '') . ' regexp ' . (
            $isMysql5x
                ? $sqlRight
                : 'concat(' . $this->escapeStringLiteral('(?s)') . ', ' . $sqlRight . ')'
        );
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({} separator ' . $this->escapeStringLiteral($separator) . ')', [$field]);
    }
}
