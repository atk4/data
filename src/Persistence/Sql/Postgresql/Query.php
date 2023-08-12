<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    protected string $identifierEscapeChar = '"';
    protected string $expressionClass = Expression::class;

    protected string $templateUpdate = 'update [table][join] set [set] [where]';
    protected string $templateReplace;

    protected function _subrenderCondition(array $row): string
    {
        if (count($row) >= 3) {
            [$field, $cond, $value] = $row;
            if (in_array(strtolower($cond), ['like', 'not like', 'regexp', 'not regexp'], true)) {
                $field = $this->expr('CAST([] AS citext)', [$field]);
                $row = [$field, $cond, $value];
            }
        }

        return parent::_subrenderCondition($row);
    }

    protected function _renderLimit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        return ' limit ' . (int) $this->args['limit']['cnt']
            . ' offset ' . (int) $this->args['limit']['shift'];
    }

    public function groupConcat($field, string $separator = ','): BaseExpression
    {
        return $this->expr('string_agg({}, [])', [$field, $separator]);
    }
}
