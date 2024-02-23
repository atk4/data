<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Optimizer;

use Atk4\Data\Persistence\Sql\Exception;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Persistence\Sql\Query;

class Util
{
    private function __construct() {}

    /**
     * @return string|false
     */
    private static function tryUnquoteSingleIdentifier(string $str)
    {
        if (preg_match('~^[\w\x80-\xf7]+$~', $str)) { // unquoted identifier
            return $str;
        }

        $openQuoteChar = substr($str, 0, 1);
        $closeQuoteChar = $openQuoteChar;
        if ($openQuoteChar === '[') { // for MSSQL
            $strQuoteCharTrimmed = preg_replace('~^\[(.+)\]$~s', '$1', $str);
            $closeQuoteChar = ']';
        } else {
            $strQuoteCharTrimmed = preg_replace('~^(["`])(.+)\1$~s', '$2', $str);
        }

        if ($str === $openQuoteChar . str_replace($openQuoteChar, $openQuoteChar . $openQuoteChar, $strQuoteCharTrimmed) . $closeQuoteChar) {
            return $strQuoteCharTrimmed;
        }

        return false;
    }

    /**
     * Parse a, a.b, a."b", "a"."b" etc.
     *
     * WARNING: it can return array if the input is part of another expression which uses it as a string value
     *
     * @param Expressionable|string $expr
     *
     * @return array{string|null, string}|false
     */
    public static function tryParseIdentifier($expr)
    {
        if ($expr instanceof Query) {
            return false;
        } elseif ($expr instanceof Expression) {
            $str = $expr->render()[0];
        } elseif ($expr instanceof Expressionable) {
            $str = (new Expression('[]', [$expr]))->render()[0]; // @phpstan-ignore-line @TODO not sure what to do here !!!
        } else {
            $str = $expr;
        }

        $parts = preg_split('~\.(?=["`[]|\w+$)~', $str, 2);
        $parts[0] = self::tryUnquoteSingleIdentifier($parts[0]);
        if ($parts[0] === false) {
            return false;
        }

        if (isset($parts[1])) {
            $parts[1] = self::tryUnquoteSingleIdentifier($parts[1]);
            if ($parts[1] === false) {
                return false;
            }
        } else {
            array_unshift($parts, null);
        }

        return $parts;
    }

    /**
     * Returns true only if the expression is a single identifier (a or "a", but not "a"."b" or *).
     *
     * WARNING: it can return true if the input is part of another expression which uses it as a string value
     *
     * @param Expressionable|string $expr
     */
    public static function isSingleIdentifier($expr): bool
    {
        $v = static::tryParseIdentifier($expr);

        return $v !== false && $v[0] === null;
    }

    /**
     * @param Expressionable|string $expr
     */
    public static function parseSingleIdentifier($expr): string
    {
        $v = static::tryParseIdentifier($expr);
        if ($v === false) {
            throw (new Exception('Invalid SQL identifier'))
                ->addMoreInfo('expr', $expr);
        } elseif ($v[0] !== null) {
            throw (new Exception('Single SQL identifier without table name required'))
                ->addMoreInfo('expr', $expr);
        }

        return $v[1];
    }

    /**
     * @param string|null $alias
     * @param mixed       $v
     *
     * @return mixed
     */
    public static function parseSelectQueryTraverseValue(Expression $exprFactory, string $argName, $alias, $v)
    {
        // expand all Expressionable objects to Expression
        if ($v instanceof Expressionable && !$v instanceof Expression) {
            $v = $v->getDsqlExpression($exprFactory);
        }

        if (is_array($v)) {
            $res = [];
            foreach ($v as $k => $v2) {
                $res[$k] = static::parseSelectQueryTraverseValue($exprFactory, $argName, is_int($k) ? null : $k, $v2);
            }

            return $res;
        } elseif ($v instanceof Query) {
            return static::parseSelectQuery($v, $alias);
        }

        return $v;
    }

    public static function parseSelectQuery(Query $query, ?string $tableAlias): ParsedSelect
    {
        $query->args['is_select_parsed'] = [true];
        $select = new ParsedSelect($query, $tableAlias);
        if (is_string($select->expr)) {
            return $select;
        }

        // traverse $query and parse everything into ParsedSelect/ParsedColumn
        foreach ($query->args as $argName => $args) {
            foreach ($args as $alias => $v) {
                $query->args[$argName][$alias] = static::parseSelectQueryTraverseValue(
                    $query->expr(),
                    $argName,
                    is_int($alias) ? null : $alias,
                    $v
                );
            }
        }

        return $select;
    }

    public static function isSelectQueryParsed(Query $query): bool
    {
        return ($query->args['is_select_parsed'] ?? [])[0] ?? false;
    }

    public static function parseColumn(Expression $expr, string $columnAlias): ParsedColumn
    {
        $column = new ParsedColumn($expr, $columnAlias);

        return $column;
    }
}
