<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Expression;
use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    protected $template_update = 'update [table][join] set [set] [where]';
    protected $template_replace;

    protected function _sub_render_condition(array $row): string
    {
        if (count($row) >= 3) {
            [$field, $cond, $value] = $row;
            if (in_array(strtolower($cond), ['like', 'not like', 'regexp', 'not regexp'], true)) {
                $field = $this->expr('CAST([] AS citext)', [$field]);
                $row = [$field, $cond, $value];
            }
        }

        return parent::_sub_render_condition($row);
    }

    public function _render_limit(): ?string
    {
        if (!isset($this->args['limit'])) {
            return null;
        }

        return ' limit ' . (int) $this->args['limit']['cnt']
            . ' offset ' . (int) $this->args['limit']['shift'];
    }

    public function groupConcat($field, string $delimiter = ','): Expression
    {
        return $this->expr('string_agg({}, [])', [$field, $delimiter]);
    }
}
