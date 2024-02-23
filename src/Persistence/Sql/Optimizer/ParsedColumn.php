<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Optimizer;

use Atk4\Data\Persistence\Sql\Expression;

class ParsedColumn
{
    /** @var Expression|string */
    public $expr;
    /** @var string|null not-null iff expr is a string */
    public $exprTableAlias;
    /** @var string */
    public $columnAlias;

    public function __construct(Expression $expr, string $columnAlias)
    {
        $exprIdentifier = Util::tryParseIdentifier($expr);
        if ($exprIdentifier !== false) {
            $this->exprTableAlias = $exprIdentifier[0];
            $this->expr = $exprIdentifier[1];
        } else {
            $this->expr = $expr;
        }

        $this->columnAlias = Util::parseSingleIdentifier($columnAlias);
    }

    public function getDsqlExpression(): Expression
    {
        if ($this->exprTableAlias !== null) {
            return new Expression('{}.{} {}', [$this->expr, $this->exprTableAlias, $this->columnAlias]); // @phpstan-ignore-line @TODO not sure what to do here !!!
        }

        return new Expression('{} {}', [$this->expr, $this->columnAlias]); // @phpstan-ignore-line @TODO not sure what to do here !!!
    }
}
