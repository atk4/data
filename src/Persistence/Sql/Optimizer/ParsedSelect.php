<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Optimizer;

use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query;

class ParsedSelect
{
    /** @var string */
    public const TOP_QUERY_ALIAS = '__atk4_top_query__';

    /** @var Query|string */
    public $expr;
    /** @var string */
    public $tableAlias;

    /**
     * @param Query|string $expr
     */
    public function __construct($expr, string $tableAlias)
    {
        $exprIdentifier = Util::tryParseIdentifier($expr);
        if ($exprIdentifier !== false) {
            $this->expr = Util::parseSingleIdentifier($expr);
        } else {
            $this->expr = $expr;
        }

        $this->tableAlias = Util::parseSingleIdentifier($tableAlias);
    }

    /*
    public function getDsqlExpression(): Expression
    {
        if ($this->tableAlias === self::TOP_QUERY_ALIAS) {
            return new Expression('{}', [$this->expr]);
        }

        return new Expression('{} {}', [$this->expr, $this->tableAlias]);
    }
    */
}
