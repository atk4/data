<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;
use Atk4\Data\Persistence\Sql\RawExpression;
use Doctrine\DBAL\Types\Type;

class Query extends BaseQuery
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = Expression::QUOTED_TOKEN_REGEX;

    protected string $identifierEscapeChar = '`';
    protected string $expressionClass = Expression::class;

    protected string $templateUpdate = 'update [table][join] set [set] [where]';

    private function isServerMysql5x(): bool
    {
        return !Connection::isServerMariaDb($this->connection) && version_compare($this->connection->getServerVersion(), '6.0') < 0;
    }

    #[\Override]
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        if ($this->isServerMysql5x()) {
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
        return $sqlLeft . ($negated ? ' not' : '') . ' regexp ' . (
            $this->isServerMysql5x()
                ? 'concat(' . $this->escapeStringLiteral('@?') . ', ' . $sqlRight . ')' // https://dbfiddle.uk/diAepf8V
                : 'concat(' . $this->escapeStringLiteral('(?s)') . ', ' . $sqlRight . ')'
        );
    }

    #[\Override]
    public function groupConcat($field, string $separator = ',')
    {
        return $this->expr('group_concat({} separator ' . $this->escapeStringLiteral($separator) . ')', [$field]);
    }

    #[\Override]
    public function fxJsonArray(array $values)
    {
        if (!Connection::isServerMariaDb($this->connection) && version_compare($this->connection->getServerVersion(), '5.7.8') < 0) {
            return parent::fxJsonArray($values);
        }

        return $this->expr('json_array(' . implode(', ', array_fill(0, count($values), '[]')) . ')', [
            ...$values,
        ]);
    }

    #[\Override]
    public function fxJsonArrayAgg(Expressionable $value)
    {
        if (version_compare($this->connection->getServerVersion(), Connection::isServerMariaDb($this->connection) ? '10.5' : '5.7.22') < 0) {
            return parent::fxJsonArrayAgg($value);
        }

        return $this->expr('json_arrayagg([])', [$value]);
    }

    /**
     * @return ($forJsonValue is true ? array{string, string, string|null} : string)
     */
    private function makeReturningClauseType(string $type, bool $forJsonValue = false)
    {
        $defType = Type::getType($type)->getSQLDeclaration($this->connection->makeDefaultColumnOptions($type), $this->connection->getDatabasePlatform());
        if ($type === 'json' && Connection::isServerMariaDb($this->connection)) { // TODO remove once DBAL 3.x support is dropped
            $defType = 'JSON';
        }
        $defCollation = preg_match('~char|text~i', $defType)
            // https://github.com/atk4/data/blob/6.0.0/src/Schema/Migrator.php#L128
            // https://github.com/doctrine/dbal/blob/3.10.2/src/Platforms/AbstractMySQLPlatform.php#L597
            ? 'utf8mb4_unicode_ci'
            : null;

        if ($forJsonValue) {
            $castTypeNonChar = ['boolean' => 'UNSIGNED', 'bigint' => 'SIGNED', 'float' => 'DOUBLE', 'json' => 'JSON'][$type] ?? null;

            assert(($defCollation === null) !== ($castTypeNonChar === null));

            if ($type === 'json') {
                return ['', '', null];
            }

            if (Connection::isServerMariaDb($this->connection)) {
                return ['', '', $castTypeNonChar];
            }

            return $castTypeNonChar !== null
                ? [' returning ' . $castTypeNonChar, '', null]
                : [' returning CHAR', ' COLLATE ' . $this->escapeIdentifier($defCollation), null];
        }

        return $defType . ($defCollation !== null ? ' COLLATE ' . $this->escapeIdentifier($defCollation) : '');
    }

    private function replaceNullJsonToNull(BaseExpression $v): BaseExpression
    {
        return $this->expr(
            'case when json_type({v}) != [] then {v} end',
            ['v' => $v, new RawExpression($this->escapeStringLiteral('NULL'))]
        );
    }

    #[\Override]
    public function fxJsonValue(Expressionable $json, string $path, string $type)
    {
        if ($this->isServerMysql5x()) {
            return parent::fxJsonValue($json, $path, $type);
        }

        $returningTypeParts = $this->makeReturningClauseType($type, true);

        $res = $type === 'json'
            ? $this->replaceNullJsonToNull($this->expr(
                'json_extract('
                    . (Connection::isServerMariaDb($this->connection) ? '[]' : 'cast([] as JSON)')
                    . ', []' . $returningTypeParts[0] . ')' . $returningTypeParts[1],
                [$json, new RawExpression($this->escapeStringLiteral($path))]
            ))
            : $this->expr(
                'json_value('
                    . (Connection::isServerMariaDb($this->connection) ? '[]' : 'cast([] as JSON)')
                    . ', []' . $returningTypeParts[0] . ')' . $returningTypeParts[1],
                [$json, new RawExpression($this->escapeStringLiteral($path))]
            );

        return $returningTypeParts[2] !== null
            ? $this->expr('cast([] as ' . $returningTypeParts[2] . ')', [$res])
            : $res;
    }

    #[\Override]
    public function jsonTable(Expressionable $json, array $columns, string $rowsPath = '$[*]')
    {
        if ($this->isServerMysql5x() || (Connection::isServerMariaDb($this->connection) && version_compare($this->connection->getServerVersion(), '10.6') < 0)) {
            return parent::jsonTable($json, $columns, $rowsPath);
        }

        $query = $this->dsql();
        $i = 0;
        $defTemplates = [];
        $defParams = [];
        foreach ($columns as $k => $column) {
            $v = $query->expr('{}', ['c' . $i]);
            if ($column['type'] === 'json' && Connection::isServerMariaDb($this->connection)) {
                $v = $this->replaceNullJsonToNull($v);
            }
            $query->field($v, $k);

            $defTemplates[] = '{} ' . $this->makeReturningClauseType($column['type']) . ' path []';
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
}
