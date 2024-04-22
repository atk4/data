<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $identifierEscapeChar = '`';
    protected string $expressionClass = Expression::class;

    protected string $templateTruncate = 'delete [from] [tableNoalias]';

    #[\Override]
    protected function _renderConditionBinaryReuse(
        string $sqlLeft,
        string $sqlRight,
        \Closure $makeSqlFx,
        bool $allowReuseLeft = true,
        bool $allowReuseRight = true,
        string $internalIdentifier = 'reuse'
    ): string {
        // https://sqlite.org/forum/info/c9970a37edf11cd1
        if (version_compare(Connection::getDriverVersion(), '3.45') < 0) {
            $allowReuseLeft = false;
            $allowReuseRight = false;
        }

        return parent::_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            $makeSqlFx,
            $allowReuseLeft,
            $allowReuseRight,
            $internalIdentifier
        );
    }

    private function _renderConditionBinaryCheckNumericSql(string $sql): string
    {
        return 'typeof(' . $sql . ') in (' . $this->escapeStringLiteral('integer')
            . ', ' . $this->escapeStringLiteral('real') . ')';
    }

    /**
     * https://dba.stackexchange.com/questions/332585/sqlite-comparison-of-the-same-operand-types-behaves-differently
     * https://sqlite.org/forum/info/5f1135146fbc37ab .
     */
    #[\Override]
    protected function _renderConditionBinary(string $operator, string $sqlLeft, string $sqlRight): string
    {
        $allowCastRight = !in_array($operator, ['in', 'not in'], true);

        return $this->_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) use ($operator, $allowCastRight) {
                $res = 'case when ' . $this->_renderConditionBinaryCheckNumericSql($sqlLeft)
                    . ' then ' . parent::_renderConditionBinary($operator, 'cast(' . $sqlLeft . ' as numeric)', $sqlRight)
                    . ' else ';
                if ($allowCastRight) {
                    $res .= 'case when ' . $this->_renderConditionBinaryCheckNumericSql($sqlRight)
                        . ' then ' . parent::_renderConditionBinary($operator, $sqlLeft, 'cast(' . $sqlRight . ' as numeric)')
                        . ' else ';
                }
                $res .= parent::_renderConditionBinary($operator, $sqlLeft, $sqlRight);
                if ($allowCastRight) {
                    $res .= ' end';
                }
                $res .= ' end';

                return $res;
            },
            true,
            $allowCastRight,
            'affinity'
        );
    }

    #[\Override]
    protected function _renderConditionInOperator(bool $negated, string $sqlLeft, array $sqlValues): string
    {
        return ($negated ? ' not' : '') . '('
            . implode(' or ', array_map(fn ($v) => $this->_renderConditionBinary('=', $sqlLeft, $v), $sqlValues))
            . ')';
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({}, [])', [$field, $separator]);
    }
}
