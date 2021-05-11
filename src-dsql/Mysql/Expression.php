<?php

declare(strict_types=1);

namespace Atk4\Dsql\Mysql;

use Atk4\Dsql\Expression as BaseExpression;

class Expression extends BaseExpression
{
    protected $escape_char = '`';
}
