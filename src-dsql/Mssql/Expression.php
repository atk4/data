<?php

declare(strict_types=1);

namespace Atk4\Dsql\Mssql;

use Atk4\Dsql\Expression as BaseExpression;

class Expression extends BaseExpression
{
    use ExpressionTrait;

    protected $escape_char = ']';
}
