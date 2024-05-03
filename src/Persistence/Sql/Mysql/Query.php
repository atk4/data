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
        $isMysql5x = !Connection::isServerMariaDb($this->connection)
            && Connection::getServerMinorVersion($this->connection) < 600;

        if ($isMysql5x) {
            $replaceSqlFx = function (string $sql, string $search, string $replacement) {
                return 'replace(' . $sql . ', ' . $this->escapeStringLiteral($search) . ', ' . $this->escapeStringLiteral($replacement) . ')';
            };

            // workaround missing regexp_replace() function
            $sqlRightEscaped = $sqlRight;
            foreach (['\\', '_', '%'] as $v) {
                $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '\\' . $v, '\\' . $v . '*');
            }
            $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '\\', '\\\\');
            foreach (['_', '%', '\\'] as $v) {
                $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '\\\\' . str_replace('\\', '\\\\', $v) . '*', '\\' . $v);
            }

            // workaround https://bugs.mysql.com/bug.php?id=84118
            // https://bugs.mysql.com/bug.php?id=63829
            // https://bugs.mysql.com/bug.php?id=68901
            // https://www.db-fiddle.com/f/argVwuJuqjFAALqfUSTEJb/0
            $sqlRightEscaped = $replaceSqlFx($sqlRightEscaped, '%\\', '%\\\\');
        } else {
            $sqlRightEscaped = 'regexp_replace(' . $sqlRight . ', '
                . $this->escapeStringLiteral('\\\\\\\|\\\(?![_%])') . ', '
                . $this->escapeStringLiteral('\\\\\\\\') . ')';
        }

        return $sqlLeft . ($negated ? ' not' : '') . ' like ' . $sqlRightEscaped
            . ' escape ' . $this->escapeStringLiteral('\\');
    }

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        $isMysql5x = !Connection::isServerMariaDb($this->connection)
            && Connection::getServerMinorVersion($this->connection) < 600;

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
