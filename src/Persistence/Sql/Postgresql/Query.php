<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;
use Atk4\Data\Persistence\Sql\RawExpression;
use Doctrine\DBAL\Types\Type;

class Query extends BaseQuery
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = Expression::QUOTED_TOKEN_REGEX;

    protected string $identifierEscapeChar = '"';
    protected string $expressionClass = Expression::class;

    protected string $templateUpdate = 'update [table][join] set [set] [where]';
    protected string $templateReplace;
    protected string $templateTruncate = 'truncate table [tableNoalias] restart identity';

    /**
     * @param \Closure(string, string): string $makeSqlFx
     */
    private function _renderConditionConditionalCastToText(string $sqlLeft, string $sqlRight, \Closure $makeSqlFx): string
    {
        return $this->_renderConditionBinaryReuse(
            $sqlLeft,
            $sqlRight,
            function ($sqlLeft, $sqlRight) use ($makeSqlFx) {
                $iifByteaSqlFx = function ($valueSql, $trueSql, $falseSql) {
                    return 'case when pg_typeof(' . $valueSql . ') = ' . $this->escapeStringLiteral('bytea') . '::regtype'
                        . ' then ' . $trueSql . ' else ' . $falseSql . ' end';
                };

                $escapeNonUtf8Fx = function ($sql, $neverBytea = false) use ($iifByteaSqlFx) {
                    $doubleBackslashesFx = function ($sql) {
                        return 'replace(' . $sql . ', ' . $this->escapeStringLiteral('\\')
                            . ', ' . $this->escapeStringLiteral('\\\\') . ')';
                    };

                    $byteaSql = 'cast(' . $doubleBackslashesFx('cast(' . $sql . ' as text)') . ' as bytea)';
                    if (!$neverBytea) {
                        $byteaSql = $iifByteaSqlFx(
                            $sql,
                            'decode(' . $iifByteaSqlFx(
                                $sql,
                                $doubleBackslashesFx('substring(cast(' . $sql . ' as text) from 3)'),
                                $this->escapeStringLiteral('')
                            ) . ', ' . $this->escapeStringLiteral('hex') . ')',
                            $byteaSql
                        );
                    }

                    // 0x00 and 0x80+ bytes will be escaped as "\xddd"
                    $res = 'encode(' . $byteaSql . ', ' . $this->escapeStringLiteral('escape') . ')';

                    // replace backslash in "\xddd" for LIKE/REGEXP
                    $res = 'regexp_replace(' . $res . ', '
                        . $this->escapeStringLiteral('(?<!\\\)((\\\\\\\)*)\\\(\d\d\d)') . ', '
                        . $this->escapeStringLiteral("\\1\u{00a9}\\3\u{00a9}") . ', '
                        . $this->escapeStringLiteral('g') . ')';

                    // revert double backslashes
                    $res = 'replace(' . $res . ', ' . $this->escapeStringLiteral('\\\\')
                        . ', ' . $this->escapeStringLiteral('\\') . ')';

                    return $res;
                };

                return $iifByteaSqlFx(
                    $sqlLeft,
                    $makeSqlFx($escapeNonUtf8Fx($sqlLeft), $escapeNonUtf8Fx($sqlRight)),
                    $makeSqlFx('cast(' . $sqlLeft . ' as citext)', 'cast(' . $sqlRight . ' as citext)')
                );
            }
        );
    }

    #[\Override]
    protected function _renderConditionLikeOperator(bool $negated, string $sqlLeft, string $sqlRight): string
    {
        return ($negated ? 'not ' : '') . $this->_renderConditionConditionalCastToText($sqlLeft, $sqlRight, function ($sqlLeft, $sqlRight) {
            $sqlRightEscaped = 'regexp_replace(' . $sqlRight . ', '
                . $this->escapeStringLiteral('(\\\[\\\_%])|(\\\)') . ', '
                . $this->escapeStringLiteral('\1\2\2') . ', '
                . $this->escapeStringLiteral('g') . ')';

            return $sqlLeft . ' like ' . $sqlRightEscaped
                . ' escape ' . $this->escapeStringLiteral('\\');
        });
    }

    // needed for PostgreSQL v14 or lower
    #[\Override]
    protected function _renderConditionRegexpOperator(bool $negated, string $sqlLeft, string $sqlRight, bool $binary = false): string
    {
        return ($negated ? 'not ' : '') . $this->_renderConditionConditionalCastToText($sqlLeft, $sqlRight, static function ($sqlLeft, $sqlRight) {
            return $sqlLeft . ' ~ ' . $sqlRight;
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

    private function jsonTableToXml(string $json): string
    {
        $rows = json_decode($json, true, 512, \JSON_BIGINT_AS_STRING | \JSON_THROW_ON_ERROR);

        return '<t>'
            . implode('', array_map(function ($row) {
                assert($row === array_values($row));

                $parts = [];
                foreach ($row as $i => $v) {
                    if ($v === null) {
                        continue;
                    }

                    $vStr = \Closure::bind(fn () => $this->castGetValue($v), $this, BaseExpression::class)();

                    $parts[] = ' c' . $i . '="'
                        . preg_replace_callback('~[\x00-\x1f"&<\x7f]~', static fn ($matches) => '&#x' . dechex(ord($matches[0])) . ';', $vStr)
                        . '"';
                }

                return '<r' . implode('', $parts) . '/>';
            }, $rows))
            . '</t>';
    }

    #[\Override]
    protected function makeArrayTable(array $rows, array $columnTypes)
    {
        $jsonData = [];
        foreach ($rows as $row) {
            $jsonRow = [];
            foreach ($row as $v) {
                $jsonRow[] = $v;
            }
            $jsonData[] = $jsonRow;
        }

        $json = json_encode($jsonData, \JSON_PRESERVE_ZERO_FRACTION | \JSON_UNESCAPED_UNICODE | \JSON_THROW_ON_ERROR);

        $asXml = version_compare($this->connection->getServerVersion(), '17.0') < 0;

        $query = $this->connection->dsql();
        $i = 0;
        $defTemplates = [];
        $defParams = [];
        foreach ($columnTypes as $k => $type) {
            $query->field($query->expr('{}', ['c' . $i]), $k);

            $defTemplates[] = '{} ' . Type::getType($type)->getSQLDeclaration([], $this->connection->getDatabasePlatform()) . ' path []';
            $defParams[] = 'c' . $i;
            $defParams[] = new RawExpression($this->escapeStringLiteral($asXml ? '@c' . $i : '$[' . $i . ']'));

            ++$i;
        }

        if ($asXml) {
            $xml = $this->jsonTableToXml($json);

            $query->table($this->expr(
                'xmltable([] passing xmlparse(document []) columns ' . implode(', ', $defTemplates) . ')',
                [new RawExpression($this->escapeStringLiteral('/t/r')), $xml, ...$defParams]
            ), 't');
        } else {
            $query->table($this->expr(
                'json_table([], [] columns (' . implode(', ', $defTemplates) . '))',
                [$json, new RawExpression($this->escapeStringLiteral('$[*]')), ...$defParams]
            ), 't');
        }

        return $query;
    }
}
