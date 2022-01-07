<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Query as BaseQuery;

class Query extends BaseQuery
{
    use ExpressionTrait;

    protected $escape_char = '`';

    protected $expression_class = Expression::class;

    protected $template_update = 'update [table][join] set [set] [where]';

    public function groupConcat($field, string $delimiter = ',')
    {
        // TODO fix mysqli, separator from bound param does not work
        // then reenable both testGroupConcat() tests
        // https://github.com/php/php-src/issues/7903
        if (!$this->hasNativeNamedParamSupport()) {
            return $this->expr('group_concat({} separator \'' . $delimiter . '\')', [$field]);
        }

        return $this->expr('group_concat({} separator [])', [$field, $delimiter]);
    }
}
