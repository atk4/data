<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;
use Atk4\Data\Persistence\Sql\RawExpression;
use Doctrine\DBAL\Types\Type;

class Query extends BaseQuery
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = Expression::QUOTED_TOKEN_REGEX;

    protected string $identifierEscapeChar = ']';
    protected string $expressionClass = Expression::class;

    protected string $templateInsert = <<<'EOF'
        begin try
          insert[option] into [tableNoalias][setFields] [setValues];
        end try begin catch
          if ERROR_NUMBER() = 544 begin
            set IDENTITY_INSERT [tableNoalias] on;
            begin try
              insert[option] into [tableNoalias][setFields] [setValues];
              set IDENTITY_INSERT [tableNoalias] off;
            end try begin catch
              set IDENTITY_INSERT [tableNoalias] off;
              throw;
            end catch
          end else begin
            throw;
          end
        end catch
        EOF;

    /**
     * @param \Closure(string, string): string $makeSqlFx
     */
    protected function _renderConditionBinaryReuseBool(string $sqlLeft, string $sqlRight, \Closure $makeSqlFx, bool $nullFromArgsOnly = false): string
    {
        $reuse = $this->_renderConditionBinaryReuse($sqlLeft, $sqlRight, static fn () => '') !== '';

        return $this->_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            static function ($sqlLeft, $sqlRight) use ($reuse, $makeSqlFx, $nullFromArgsOnly) {
                $res = $makeSqlFx($sqlLeft, $sqlRight);

                if ($reuse) {
                    $res = 'iif(not(' . $res . '), 0, iif('
                        . ($nullFromArgsOnly ? $sqlLeft . ' is not null and ' . $sqlRight . ' is not null' : $res)
                        . ', 1, null))';
                }

                return $res;
            }
        ) . ($reuse ? ' = 1' : '');
    }

    #[\Override]
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        return ($negated ? 'not ' : '') . $this->_renderConditionBinaryReuseBool(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) {
                $iifNtextFx = static function ($valueSql, $trueSql, $falseSql) {
                    $isNtextFx = static function ($sql, $negate) {
                        // "select top 0 ..." is always optimized into constant expression
                        return 'datalength(concat((select top 0 ' . $sql . '), 0x' . bin2hex('0') . ')) '
                            . ($negate ? '!' : '') . '= 2';
                    };

                    return '((' . $isNtextFx($valueSql, false) . ' and ' . $trueSql . ')'
                        . ' or (' . $isNtextFx($valueSql, true) . ' and ' . $falseSql . '))';
                };

                $iifBinaryFx = static function ($valueSql, $trueSql, $falseSql) {
                    $isBinaryFx = static function ($sql, $negate) {
                        // "select top 0 ..." is always optimized into constant expression
                        return 'isnull((select top 0 ' . $sql . '), 0x' . bin2hex('A') . ') '
                            . ($negate ? '' : '!') . '= 0x' . bin2hex('a');
                    };

                    return '((' . $isBinaryFx($valueSql, false) . ' and ' . $trueSql . ')'
                        . ' or (' . $isBinaryFx($valueSql, true) . ' and ' . $falseSql . '))';
                };

                $makeSqlFx = function ($isNtext, $isBinary) use ($sqlLeft, $sqlRight) {
                    $quoteStringFx = fn (string $v) => $isNtext
                        ? $this->escapeStringLiteral($v)
                        : '0x' . bin2hex($v);

                    $replaceFx = static function (string $sql, string $search, string $replacement) use ($quoteStringFx) {
                        return 'replace(' . $sql . ', '
                            . $quoteStringFx($search) . ', '
                            . $quoteStringFx($replacement) . ')';
                    };

                    // workaround missing regexp_replace() function
                    // https://devblogs.microsoft.com/azure-sql/introducing-regular-expression-regex-support-in-azure-sql-db/
                    $sqlRightEscaped = $sqlRight;
                    foreach (['\\', '_', '%'] as $v) {
                        $sqlRightEscaped = $replaceFx($sqlRightEscaped, '\\' . $v, '\\' . $v . '*');
                    }
                    $sqlRightEscaped = $replaceFx($sqlRightEscaped, '\\', '\\\\');
                    foreach (['_', '%', '\\'] as $v) {
                        $sqlRightEscaped = $replaceFx($sqlRightEscaped, '\\\\' . str_replace('\\', '\\\\', $v) . '*', '\\' . $v);
                    }

                    $sqlRightEscaped = $replaceFx($sqlRightEscaped, '[', '\[');

                    return $sqlLeft . ' like ' . $sqlRightEscaped
                        . ($isBinary ? ' collate Latin1_General_BIN' : '')
                        . ' escape ' . $quoteStringFx('\\');
                };

                return $iifNtextFx(
                    $sqlLeft,
                    $makeSqlFx(true, false),
                    $iifBinaryFx(
                        $sqlLeft,
                        $makeSqlFx(false, true),
                        $makeSqlFx(false, false)
                    )
                );
            },
            true
        );
    }

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        return ($negated ? 'not ' : '') . $this->_renderConditionBinaryReuseBool(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) use ($binary) {
                $castToStringFx = static function ($sql) {
                    return 'case when ' . $sql . ' is not null then '
                        . 'concat(' . $sql . ', substring(char(' . ord('0') . '), 1, 0)) end';
                };

                return 'regexp_like(' . $castToStringFx($sqlLeft) . ', ' . $sqlRight
                    . ', cast(' . $this->escapeStringLiteral(($binary ? '' : 'i') . 's') . ' as varchar))';
            },
            true
        );
    }

    #[\Override]
    protected function _subrenderCondition(array $row): string
    {
        if (count($row) !== 1) {
            [$field, $operator, $value] = $row;
            $operatorLc = strtolower($operator ?? '=');

            if ($field instanceof Field && in_array($field->type, ['binary', 'blob'], true)
                && in_array($operatorLc, ['regexp', 'not regexp'], true)
            ) {
                throw (new Exception('Unsupported binary field operator'))
                    ->addMoreInfo('operator', $operator)
                    ->addMoreInfo('type', $field->type);
            }
        }

        return parent::_subrenderCondition($row);
    }

    #[\Override]
    protected function deduplicateRenderOrder(array $sqls): array
    {
        $res = [];
        foreach ($sqls as $sql) {
            $sqlWithoutDirection = preg_replace('~\s+(?:asc|desc)\s*$~i', '', $sql);
            if (!isset($res[$sqlWithoutDirection])) {
                $res[$sqlWithoutDirection] = $sql;
            }
        }

        return array_values($res);
    }

    #[\Override]
    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        $cnt = (int) $this->args['limit']['cnt'];
        $shift = (int) $this->args['limit']['shift'];

        if ($cnt === 0) {
            $cnt = 1;
            $shift = \PHP_INT_MAX;
        }

        return (!isset($this->args['order']) ? ' order by (select null)' : '')
            . ' offset ' . $shift . ' rows'
            . ' fetch next ' . $cnt . ' rows only';
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('string_agg({}, ' . $this->escapeStringLiteral($separator) . ')', [$field]);
    }

    #[\Override]
    public function fxJsonArray(array $values)
    {
        if (version_compare($this->connection->getServerVersion(), '16') < 0) {
            return parent::fxJsonArray($values);
        }

        return $this->expr('json_array(' . implode(', ', array_fill(0, count($values), '[]')) . ($values !== [] ? ' null on null' : '') . ')', [
            ...$values,
        ]);
    }

    #[\Override]
    public function fxJsonArrayAgg(Expressionable $value)
    {
        if (version_compare($this->connection->getServerVersion(), '17') < 0) {
            return parent::fxJsonArrayAgg($value);
        }

        return $this->expr('json_arrayagg([] null on null)', [$value]);
    }

    #[\Override]
    public function jsonTable(Expressionable $json, array $columns, string $rowsPath = '$[*]')
    {
        assert(str_starts_with($rowsPath, '$'));
        $rowsPath = '$[0]' . substr($rowsPath, 1);

        assert(str_ends_with($rowsPath, '[*]'));
        $rowsPath = substr($rowsPath, 0, -3);

        $query = $this->dsql();
        $i = 0;
        $defTemplates = [];
        $defParams = [];
        foreach ($columns as $k => $column) {
            $query->field($query->expr('{}', ['c' . $i]), $k);

            $defTemplates[] = '{} ' . Type::getType($column['type'])->getSQLDeclaration($this->connection->makeDefaultColumnOptions($column['type']), $this->connection->getDatabasePlatform()) . ' []'
                . ($column['type'] === 'json' ? ' as json' : '');
            $defParams[] = 'c' . $i;
            $defParams[] = new RawExpression($this->escapeStringLiteral($column['path']));

            ++$i;
        }
        $query->table($this->expr(
            'openjson([], []) with (' . implode(', ', $defTemplates) . ')',
            [
                $this->fxConcat(
                    new RawExpression($this->escapeStringLiteral('[')),
                    $json,
                    new RawExpression($this->escapeStringLiteral(']')),
                ),
                new RawExpression($this->escapeStringLiteral($rowsPath)), ...$defParams,
            ]
        ), 't');

        return $query;
    }

    #[\Override]
    public function exists()
    {
        return $this->dsql()->mode('select')->field(
            $this->dsql()->expr('case when exists[] then 1 else 0 end', [$this])
        );
    }
}
