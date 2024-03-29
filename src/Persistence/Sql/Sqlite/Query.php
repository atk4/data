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

    private function _renderConditionBinaryCheckNumericSql(string $sql): string
    {
        return 'typeof(' . $sql . ') in (\'integer\', \'real\')';
    }

    /**
     * https://dba.stackexchange.com/questions/332585/sqlite-comparison-of-the-same-operand-types-behaves-differently
     * https://sqlite.org/forum/info/5f1135146fbc37ab .
     */
    #[\Override]
    protected function _renderConditionBinary(string $operator, string $sqlLeft, string $sqlRight): string
    {
        /** @var bool */
        $allowCastLeft = true;
        $allowCastRight = !in_array($operator, ['in', 'not in'], true);

        // https://sqlite.org/forum/info/c9970a37edf11cd1
        $subqueryFromSql = null;
        if (version_compare(Connection::getDriverVersion(), '3.45') >= 0) {
            $nonTrivialSqlRegex = '~\s|\(~';
            $subqueryLeftColumnSql = $allowCastLeft && preg_match($nonTrivialSqlRegex, $sqlLeft)
                ? $this->escapeIdentifier('__atk4_affinity_left__')
                : null;
            $subqueryRightColumnSql = $allowCastRight && preg_match($nonTrivialSqlRegex, $sqlRight)
                ? $this->escapeIdentifier('__atk4_affinity_right__')
                : null;

            if ($subqueryLeftColumnSql !== null || $subqueryRightColumnSql !== null) {
                $subqueryFromSql = 'select ';
                if ($subqueryLeftColumnSql !== null) {
                    $subqueryFromSql .= $sqlLeft . ' ' . $subqueryLeftColumnSql;
                    $sqlLeft = $subqueryLeftColumnSql;
                }
                if ($subqueryRightColumnSql !== null) {
                    if ($subqueryLeftColumnSql !== null) {
                        $subqueryFromSql .= ', ';
                    }
                    $subqueryFromSql .= $sqlRight . ' ' . $subqueryRightColumnSql;
                    $sqlRight = $subqueryRightColumnSql;
                }
            }
        }

        $res = '';
        if ($allowCastLeft) {
            $res .= 'case when ' . $this->_renderConditionBinaryCheckNumericSql($sqlLeft)
                . ' then ' . parent::_renderConditionBinary($operator, 'cast(' . $sqlLeft . ' as numeric)', $sqlRight)
                . ' else ';
        }
        if ($allowCastRight) {
            $res .= 'case when ' . $this->_renderConditionBinaryCheckNumericSql($sqlRight)
                . ' then ' . parent::_renderConditionBinary($operator, $sqlLeft, 'cast(' . $sqlRight . ' as numeric)')
                . ' else ';
        }
        $res .= parent::_renderConditionBinary($operator, $sqlLeft, $sqlRight);
        if ($allowCastRight) {
            $res .= ' end';
        }
        if ($allowCastLeft) {
            $res .= ' end';
        }

        if ($subqueryFromSql !== null) {
            $res = '(select ' . $res . ' from (' . $subqueryFromSql . ') ' . $this->escapeIdentifier('__atk4_affinity_tmp__') . ')';
        }

        return $res;
    }

    #[\Override]
    protected function _renderConditionInOperator(bool $negated, string $sqlLeft, array $sqlValues): string
    {
        $res = '(' . implode(' or ', array_map(fn ($v) => $this->_renderConditionBinary('=', $sqlLeft, $v), $sqlValues)) . ')';
        if ($negated) {
            $res = 'not' . $res;
        }

        return $res;
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({}, [])', [$field, $separator]);
    }
}
