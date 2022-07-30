<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;

class Expression extends BaseExpression
{
    protected string $identifierEscapeChar = '`';
}
