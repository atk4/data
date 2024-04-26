<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = Expression::QUOTED_TOKEN_REGEX;

    protected string $identifierEscapeChar = '"';
    protected string $expressionClass = Expression::class;

    protected string $templateUpdate = 'update [table][join] set [set] [where]';
    protected string $templateReplace;

    /**
     * @param \Closure(string, string): string $makeSqlFx
     */
    private function _renderConditionConditionalCastToText(string $sqlLeft, string $sqlRight, \Closure $makeSqlFx): string
    {
        return $this->_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) use ($makeSqlFx) {
                $iffByteaSqlFx = function ($valueSql, $trueSql, $falseSql) {
                    return 'case when pg_typeof(' . $valueSql . ') = ' . $this->escapeStringLiteral('bytea') . '::regtype'
                        . ' then ' . $trueSql . ' else ' . $falseSql . ' end';
                };

                $castToTextFx = function ($sql, $neverBytea = false) use ($iffByteaSqlFx) {
                    $castTextToByteaSql = 'cast(replace(cast(' . $sql . ' as text), ' . $this->escapeStringLiteral('\\')
                            . ', ' . $this->escapeStringLiteral('\\\\') . ') as bytea)';

                    $sql = $neverBytea
                        ? $castTextToByteaSql
                        : $iffByteaSqlFx(
                            $sql,
                            'decode(' . $iffByteaSqlFx(
                                $sql,
                                'substring(cast(' . $sql . ' as text) from 3)',
                                $this->escapeStringLiteral('')
                            ) . ', ' . $this->escapeStringLiteral('hex') . ')',
                            $castTextToByteaSql
                        );

                    return 'encode(' . $sql . ', ' . $this->escapeStringLiteral('escape') . ')';
                };

                return $iffByteaSqlFx(
                    $sqlLeft,
                    $makeSqlFx($castToTextFx($sqlLeft), $castToTextFx($sqlRight, true)),
                    $makeSqlFx('cast(' . $sqlLeft . ' as citext)', $sqlRight)
                );
            }
        );
    }

    #[\Override]
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        return $this->_renderConditionConditionalCastToText($sqlLeft, $sqlRight, function ($sqlLeft, $sqlRight) use ($negated) {
            $sqlRightEscaped = 'regexp_replace(' . $sqlRight . ', '
                . $this->escapeStringLiteral('(\\\[\\\_%])|(\\\)') . ', '
                . $this->escapeStringLiteral('\1\2\2') . ', '
                . $this->escapeStringLiteral('g') . ')';

            return $sqlLeft . ($negated ? ' not' : '') . ' like ' . $sqlRightEscaped
                . ' escape ' . $this->escapeStringLiteral('\\');
        });
    }

    // needed for PostgreSQL v14 and lower
    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        return $this->_renderConditionConditionalCastToText($sqlLeft, $sqlRight, static function ($sqlLeft, $sqlRight) use ($negated) {
            return $sqlLeft . ' ' . ($negated ? '!' : '') . '~ ' . $sqlRight;
        });
    }

    #[\Override]
    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        return ' limit ' . (int) $this->args['limit']['cnt']
            . ' offset ' . (int) $this->args['limit']['shift'];
    }

    #[\Override]
    public function groupConcat($field, string $separator = ','): BaseExpression
    {
        return $this->expr('string_agg({}, [])', [$field, $separator]);
    }
}
