<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected string $identifierEscapeChar = '"';
    protected string $expressionClass = Expression::class;

    protected string $templateUpdate = 'update [table][join] set [set] [where]';
    protected string $templateReplace;

    #[\Override]
    protected function _subrenderCondition(array $row): string
    {
        if (count($row) !== 1) {
            [$field, $operator, $value] = $row;

            if (in_array(strtolower($operator ?? '='), ['like', 'not like', 'regexp', 'not regexp'], true)) {
                $field = $this->expr('CAST([] AS citext)', [$field]);

                $row = [$field, $operator, $value];
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

        return ' limit ' . (int) $this->args['limit']['cnt']
            . ' offset ' . (int) $this->args['limit']['shift'];
    }

    #[\Override]
    public function groupConcat($field, string $separator = ','): BaseExpression
    {
        return $this->expr('string_agg({}, [])', [$field, $separator]);
    }
}
