<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Atk4\Data\Persistence\Sql\ExecuteException;
use Atk4\Data\Persistence\Sql\Expression as BaseExpression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;
use Atk4\Data\Persistence\Sql\RawExpression;
use Doctrine\DBAL\Connection as DbalConnection;
use Doctrine\DBAL\Exception\DatabaseObjectNotFoundException;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Types\Type;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $paramBase = 'xxaaaa';

    protected string $identifierEscapeChar = '"';
    protected string $expressionClass = Expression::class;

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
                    // for Oracle v23 or higher "CASE bool WHEN true THEN 1 ..." should be used
                    // https://dbfiddle.uk/xYhEngrA
                    $res = 'case when not(' . $res . ') then 0 else case when '
                        . ($nullFromArgsOnly ? $sqlLeft . ' is not null and ' . $sqlRight . ' is not null' : $res)
                        . ' then 1 end end';
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
                $binaryPrefix = "atk4_binary\ru5f8mzx4vsm8g2c9\r";

                $startsWithBinaryPrefixFx = function ($sql) use ($binaryPrefix) {
                    return $sql . ' like ' . $this->escapeStringLiteral($binaryPrefix . str_repeat('_', 8) . '%');
                };

                $binaryEncodeWithoutPrefixFx = static function ($sql) use ($binaryPrefix, $startsWithBinaryPrefixFx) {
                    return 'case when ' . $startsWithBinaryPrefixFx($sql) . ' then to_char(substr(' . $sql . ', ' . (strlen($binaryPrefix) + 9) . '))'
                        . ' else rawtohex(utl_raw.cast_to_raw(' . $sql . ')) end';
                };

                $replaceMultiFx = function (string $sql, array $replacements) {
                    $res = $sql;
                    foreach ($replacements as $search => $replacement) {
                        $res = 'replace(' . $res . ', '
                            . $this->escapeStringLiteral((string) $search) . ', '
                            . $this->escapeStringLiteral($replacement) . ')';
                    }

                    return $res;
                };

                return 'case when ' . $sqlLeft . ' is null or ' . $sqlRight . ' is null then null '
                    . 'when ' . $startsWithBinaryPrefixFx($sqlLeft) . ' or ' . $startsWithBinaryPrefixFx($sqlRight) . ' then '
                    . 'case when ' . $this->_renderConditionRegexpOperator(
                        false,
                        $binaryEncodeWithoutPrefixFx($sqlLeft),
                        'concat(' . $this->escapeStringLiteral('^') . ', concat(' . $replaceMultiFx(
                            $binaryEncodeWithoutPrefixFx($sqlRight),
                            [
                                bin2hex('\\\\') => 'x',
                                bin2hex('\_') => 'y',
                                bin2hex('\%') => 'z',
                                bin2hex('\\') => 'x',
                                bin2hex('_') => '..',
                                bin2hex('%') => '(..)*',
                                'x' => bin2hex('\\'),
                                'y' => bin2hex('_'),
                                'z' => bin2hex('%'),
                            ]
                        ) . ', ' . $this->escapeStringLiteral('$') . '))'
                    ) . ' then 1 else 0 end'
                    . ' else '
                    . 'case when ' . parent::_renderConditionLikeOperator(
                        false,
                        $sqlLeft,
                        $sqlRight
                    ) . ' then 1 else 0 end'
                    . ' end = 1';
            },
            true
        );
    }

    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        return ($negated ? 'not ' : '') . 'regexp_like(' . $sqlLeft . ', ' . $sqlRight
            . ', ' . $this->escapeStringLiteral(($binary ? 'c' : 'i') . 'n') . ')';
    }

    #[\Override]
    public function render(): array
    {
        if ($this->mode === 'select' && count($this->args['table'] ?? []) === 0) {
            try {
                $this->table('DUAL');

                return parent::render();
            } finally {
                unset($this->args['table']);
            }
        }

        return parent::render();
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

            if ($field instanceof Field && in_array($field->type, ['text', 'blob'], true)) {
                if (in_array($operatorLc, ['=', '!='], true)) {
                    if ($field->type === 'text') {
                        $field = $this->expr('LOWER([])', [$field]);
                        $value = $this->expr('LOWER([])', [$value]);
                    }

                    $row = [$this->expr('dbms_lob.compare([], [])', [$field, $value]), $operator, 0];
                } elseif (in_array($operatorLc, ['like', 'not like'], true)) {
                    if ($field->type === 'text') {
                        $field = $this->expr('LOWER([])', [$field]);
                        $value = $this->expr('LOWER([])', [$value]);

                        $row = [$field, $operator, $value];
                    }
                } elseif (!in_array($operatorLc, ['regexp', 'not regexp'], true)) {
                    throw (new Exception('Unsupported CLOB/BLOB field operator'))
                        ->addMoreInfo('operator', $operator)
                        ->addMoreInfo('type', $field->type);
                }
            }
        }

        return parent::_subrenderCondition($row);
    }

    #[\Override]
    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        $cnt = (int) $this->args['limit']['cnt'];
        $shift = (int) $this->args['limit']['shift'];

        return ($shift ? ' offset ' . $shift . ' rows' : '')
            . ' fetch next ' . $cnt . ' rows only';
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('listagg({field}, []) within group (order by {field})', ['field' => $field, $separator]);
    }

    #[\Override]
    public function fxConcat(...$values)
    {
        $sqlArgs = [];
        $sql = $this->makeNaryTree($values, 2, static function (array $values) use (&$sqlArgs) {
            if (count($values) === 1) {
                $sqlArgs[] = array_first($values);

                return 'TO_CLOB([])';
            }

            return 'concat(' . implode(', ', $values) . ')'; // @phpstan-ignore argument.type
        });

        return $this->expr($sql, $sqlArgs);
    }

    #[\Override]
    public function fxJsonArray(array $values)
    {
        if ($values === []) {
            return $this->expr('[]', [new RawExpression($this->escapeStringLiteral('[]'))]);
        }

        return $this->expr('json_array(' . implode(', ', array_fill(0, count($values), '[]')) . ' null on null returning CLOB)', [
            ...$values,
        ]);
    }

    #[\Override]
    public function fxJsonArrayAgg(Expressionable $value)
    {
        return $this->expr('json_arrayagg([] null on null returning CLOB)', [$value]);
    }

    private function makeReturningClauseType(string $type): string
    {
        return ['boolean' => 'NUMBER(1)', 'bigint' => 'NUMBER(20)', 'float' => 'NUMBER'][$type] ?? 'VARCHAR2';
    }

    private function makeReturningClauseAllowConversion(string $type): string
    {
        return $type === 'boolean' && version_compare($this->connection->getServerVersion(), '21.0') >= 0
            ? ' ALLOW BOOLEAN TO NUMBER'
            : '';
    }

    private function replaceNullJsonToNull(BaseExpression $v): BaseExpression
    {
        return $this->expr(
            'case when not json_equal({v}, []) then {v} end',
            ['v' => $v, new RawExpression($this->escapeStringLiteral('null'))]
        );
    }

    #[\Override]
    public function fxJsonValue(Expressionable $json, string $path, string $type)
    {
        if (version_compare($this->connection->getServerVersion(), '21.0') < 0) {
            assert(str_starts_with($path, '$'));
            $path = '$[0]' . substr($path, 1);

            $json = $this->fxConcat(
                new RawExpression($this->escapeStringLiteral('[')),
                $json,
                new RawExpression($this->escapeStringLiteral(']')),
            );
        }

        return $type === 'json'
            ? $this->replaceNullJsonToNull($this->expr('json_query([], [] returning [])', [
                $json,
                new RawExpression($this->escapeStringLiteral($path)),
                new RawExpression(Type::getType($type)->getSQLDeclaration($this->connection->makeDefaultColumnOptions($type), $this->connection->getDatabasePlatform())),
            ]))
            : $this->expr('json_value([], [] returning [])', [
                $json,
                new RawExpression($this->escapeStringLiteral($path)),
                new RawExpression($this->makeReturningClauseType($type) . $this->makeReturningClauseAllowConversion($type)),
            ]);
    }

    #[\Override]
    public function jsonTable(Expressionable $json, array $columns, string $rowsPath = '$[*]')
    {
        $query = $this->dsql();
        $i = 0;
        $defTemplates = [];
        $defParams = [];
        foreach ($columns as $k => $column) {
            $v = $query->expr('{}', ['c' . $i]);
            if ($column['type'] === 'json') {
                $v = $this->replaceNullJsonToNull($v);
            }
            $query->field($v, $k);

            $defTemplates[] = '{} '
                . $this->makeReturningClauseType($column['type'])
                . ($column['type'] === 'json' ? ' format json' : '')
                . ' path []'
                . $this->makeReturningClauseAllowConversion($column['type']);
            $defParams[] = 'c' . $i;
            $defParams[] = new RawExpression($this->escapeStringLiteral($column['path']));

            ++$i;
        }
        $query->table($this->expr(
            'json_table([], [] columns (' . implode(', ', $defTemplates) . '))',
            [$json, new RawExpression($this->escapeStringLiteral($rowsPath)), ...$defParams]
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

    #[\Override]
    protected function _execute(?object $connection, bool $fromExecuteStatement)
    {
        $res = parent::_execute($connection, $fromExecuteStatement);

        // TODO submit PR to DBAL to handle this using DB trigger
        if ($this->mode === 'truncate' && $connection instanceof DbalConnection && $this->template === $this->templateTruncate && preg_match('~^truncate table ((?:"[^"]+"\.)?"[^"]+")$~i', $this->render()[0], $matches)) {
            $platform = $connection->getDatabasePlatform();
            $pkSequenceName = str_replace('"', '', \Closure::bind(static fn () => $platform->getIdentitySequenceName($matches[1], ''), null, OraclePlatform::class)()); // @phpstan-ignore method.notFound

            $resetAutoincrementQuery = $this->expr('alter sequence {} restart', [$pkSequenceName]);

            try {
                $resetAutoincrementQuery->_execute($connection, true);
            } catch (ExecuteException $e) {
                if (!$e->getPrevious() instanceof DatabaseObjectNotFoundException) {
                    throw $e;
                }
            }
        }

        return $res;
    }
}
