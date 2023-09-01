<?php

declare(strict_types=1);

namespace Atk4\Data\Field;

use Atk4\Core\InitializerTrait;
use Atk4\Data\Field;
use Atk4\Data\Model;
use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Expressionable;
use Atk4\Data\Reference;

class SqlExpressionField extends Field
{
    use InitializerTrait {
        init as private _init;
    }

    public bool $neverSave = true;
    public bool $readOnly = true;

    /** @var \Closure(object, Expression): (string|Expressionable)|string|Expressionable Used expression. */
    public $expr;

    /** @var string Specifies how to aggregate this. */
    public $aggregate;

    /** @var string */
    public $concatSeparator;

    /** @var Reference\HasMany|null When defining as aggregate, this will point to relation object. */
    public $aggregateRelation;

    /** @var string Specifies which field to use. */
    public $field;

    /**
     * Should this field use alias?
     * Expression fields always need alias.
     */
    public function useAlias(): bool
    {
        return true;
    }

    /**
     * When field is used as expression, this method will be called.
     */
    public function getDsqlExpression(Expression $expression): Expression
    {
        $expr = $this->expr;
        if ($expr instanceof \Closure) {
            $expr = $expr($this->getOwner(), $expression);
        }

        if (is_string($expr)) {
            // If our Model has expr() method (inherited from Persistence\Sql) then use it
            if ($this->getOwner()->hasMethod('expr')) {
                return $this->getOwner()->expr('([])', [$this->getOwner()->expr($expr)]);
            }

            // Otherwise call it from expression itself
            return $expression->expr('([])', [$expression->expr($expr)]);
        } elseif ($expr instanceof Expressionable && !$expr instanceof Expression) { // @phpstan-ignore-line
            return $expression->expr('[]', [$expr]);
        }

        return $expr;
    }
}
