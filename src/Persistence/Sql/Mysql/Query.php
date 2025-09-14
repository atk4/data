<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

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
    protected function makeArrayTable(array $rows, array $columnTypes)
    {
        if ($this->isServerMysql5x()) {
            return parent::makeArrayTable($rows, $columnTypes);
        }

        $jsonData = [];
        foreach ($rows as $row) {
            $jsonRow = [];
            foreach ($row as $v) {
                $jsonRow[] = $v;
            }
            $jsonData[] = $jsonRow;
        }

        $json = json_encode($jsonData, \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $query = $this->connection->dsql();
        $i = 0;
        $defTemplates = [];
        $defParams = [];
        foreach ($columnTypes as $k => $type) {
            $query->field($query->expr('{}', ['c' . $i]), $k);

            $defType = Type::getType($type)->getSQLDeclaration([], $this->connection->getDatabasePlatform());
            $defCollation = preg_match('~char|text~i', $defType)
                // https://github.com/atk4/data/blob/6.0.0/src/Schema/Migrator.php#L128
                // https://github.com/doctrine/dbal/blob/3.10.2/src/Platforms/AbstractMySQLPlatform.php#L597
                // TODO DBAL 4.0 https://github.com/doctrine/dbal/pull/4644
                ? 'utf8mb4_unicode_ci'
                : null;
            $defTemplates[] = '{} ' . $defType . ($defCollation !== null ? ' COLLATE ' . $this->escapeIdentifier($defCollation) : '') . ' path []';
            $defParams[] = 'c' . $i;
            $defParams[] = new RawExpression($this->escapeStringLiteral('$[' . $i . ']'));

            ++$i;
        }
        $query->table($this->expr(
            'json_table([], [] columns (' . implode(', ', $defTemplates) . '))',
            [$json, new RawExpression($this->escapeStringLiteral('$[*]')), ...$defParams]
        ), 't');

        return $query;
    }
}
