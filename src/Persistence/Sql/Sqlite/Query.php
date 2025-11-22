<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\ExecuteException;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;
use Atk4\Data\Persistence\Sql\RawExpression;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Exception\TableNotFoundException;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $identifierEscapeChar = '`';
    protected string $expressionClass = Expression::class;

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
    #[\Override] // @phpstan-ignore method.childParameterType (https://github.com/phpstan/phpstan/issues/10942)
    protected function _renderConditionBinary(string $operator, string $sqlLeft, $sqlRight): string
    {
        if (in_array($operator, ['in', 'not in'], true)) {
            if (is_array($sqlRight)) {
                return ($operator === 'not in' ? ' not' : '') . '('
                    . implode(' or ', array_map(fn ($v) => $this->_renderConditionBinary('=', $sqlLeft, $v), $sqlRight))
                    . ')';
            }

            $allowCastRight = false;
        } else {
            $allowCastRight = true;
        }

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

    private function _renderConditionIsCaseInsensitive(string $sql): string
    {
        return '(select __atk4_case_v__ = ' . $this->escapeStringLiteral('a')
            . ' from (select ' . $sql . ' __atk4_case_v__ where 0 union all select '
            . $this->escapeStringLiteral('A') . ') __atk4_case_tmp__)';
    }

    #[\Override]
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        return ($negated ? 'not ' : '') . $this->_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) {
                $regexReplaceSqlFx = function (string $sql, string $search, string $replacement) {
                    return 'regexp_replace(' . $sql . ', ' . $this->escapeStringLiteral($search) . ', ' . $this->escapeStringLiteral($replacement) . ')';
                };

                return 'case '
                    // workaround "_" matching more than one byte in BLOB - https://dbfiddle.uk/Dnq8BXGy
                    . 'case when instr(' . $sqlRight . ', ' . $this->escapeStringLiteral('_') . ') != 0 then 1 else '
                    . parent::_renderConditionLikeOperator(
                        false,
                        $sqlLeft,
                        $sqlRight
                    ) . ' end when 1 then ' . $this->_renderConditionRegexpOperator(
                        false,
                        $sqlLeft,
                        'concat(' . $this->escapeStringLiteral('^') . ',' . $regexReplaceSqlFx(
                            $regexReplaceSqlFx(
                                $regexReplaceSqlFx(
                                    $regexReplaceSqlFx($sqlRight, '\\\(?:(?=[_%])|\K\\\)|(?=[.\\\+*?[^\]$(){}|])', '\\\\'),
                                    '(?<!\\\)(\\\\\\\)*\K_',
                                    '.'
                                ),
                                '(?<!\\\)(\\\\\\\)*\K%',
                                '.*'
                            ),
                            '(?<!\\\)(\\\\\\\)*\K\\\(?=[_%])',
                            ''
                        ) . ', ' . $this->escapeStringLiteral('$') . ')'
                    ) . ' when 0 then 0 end';
            }
        );
    }

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        return ($negated ? 'not ' : '') . $this->_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) {
                return 'regexp_like(' . $sqlLeft . ', ' . $sqlRight
                    . ', case when ' . $this->_renderConditionIsCaseInsensitive($sqlLeft)
                    . ' then ' . $this->escapeStringLiteral('is')
                    . ' else ' . $this->escapeStringLiteral('-us')
                    . ' end)';
            },
            true,
            false
        );
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({}, [])', [$field, $separator]);
    }

    #[\Override]
    public function fxJsonArray(array $values)
    {
        return $this->expr('json_array(' . implode(', ', array_fill(0, count($values), '[]')) . ')', [
            ...$values,
        ]);
    }

    #[\Override]
    public function fxJsonArrayAgg(Expressionable $value)
    {
        return $this->expr('json_group_array([])', [$value]);
    }

    #[\Override]
    public function fxJsonValue(Expressionable $json, string $path, string $type, ?Expressionable $jsonRootType = null)
    {
        $jsonType = $jsonRootType !== null && $path === '$'
            ? $jsonRootType
            : $this->expr('json_type([], [])', [$json, new RawExpression($this->escapeStringLiteral($path))]);

        return $type === 'json'
            ? $this->expr('case [jsonType]'
                . ' when [] then json_quote([jsonValue])'
                . ' when [false] then [false]'
                . ' when [true] then [true]'
                . ' else [jsonValue]'
                . ' end', [
                    'jsonType' => $jsonType,
                    'jsonValue' => $jsonRootType !== null && $path === '$'
                        ? $json
                        : $this->expr('json_extract([], [])', [$json, new RawExpression($this->escapeStringLiteral($path))]),
                    new RawExpression($this->escapeStringLiteral('text')),
                    'false' => new RawExpression($this->escapeStringLiteral('false')),
                    'true' => new RawExpression($this->escapeStringLiteral('true')),
                ])
            : $this->expr('case when [jsonType] not in([], []) then json_extract([json], [path]) end', [
                'json' => $json,
                'jsonType' => $jsonType,
                'path' => new RawExpression($this->escapeStringLiteral($path)),
                new RawExpression($this->escapeStringLiteral('array')),
                new RawExpression($this->escapeStringLiteral('object')),
            ]);
    }

    #[\Override]
    public function jsonTable(Expressionable $json, array $columns, string $rowsPath = '$[*]')
    {
        assert(str_ends_with($rowsPath, '[*]'));
        $rowsPath = substr($rowsPath, 0, -3);

        $query = $this->dsql();
        foreach ($columns as $k => $column) {
            $query->field($this->fxJsonValue($this->expr('{}', ['value']), $column['path'], $column['type'], $this->expr('{}', ['type'])), $k);
        }
        $query->table($this->expr('json_each([], [])', [$json, new RawExpression($this->escapeStringLiteral($rowsPath))]));
        $query->where('key', '!=', null);

        return $query;
    }

    #[\Override]
    protected function _execute(?object $connection, bool $fromExecuteStatement)
    {
        // workaround https://sqlite.org/forum/forumpost/e434490a01
        if ($this->mode === 'truncate' && $connection instanceof DbalConnection && $this->template === $this->templateTruncate && preg_match('~^truncate table (?:`([^`]+)`\.)?`([^`]+)`$~i', $this->render()[0], $matches)) {
            $this->template = 'delete [from] [tableNoalias]';
            try {
                $res = parent::_execute($connection, $fromExecuteStatement);
            } finally {
                $this->template = $this->templateTruncate;
            }

            $resetAutoincrementQuery = $this->dsql()
                ->mode('delete')
                ->table(($matches[1] !== '' ? $matches[1] : 'main') . '.sqlite_sequence')
                ->where('name', $matches[2]);

            try {
                $resetAutoincrementQuery->_execute($connection, true);
            } catch (ExecuteException $e) {
                if (!$e->getPrevious() instanceof TableNotFoundException) {
                    throw $e;
                }
            }

            return $res;
        }

        return parent::_execute($connection, $fromExecuteStatement);
    }
}
