<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;

class Expression extends BaseExpression
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = <<<'EOF'
        (?:(?sx)
            '(?:[^'\\]+|\\.|'')*+'
            |"(?:[^"\\]+|\\.|"")*+"
            |`(?:[^`]+|``)*+`
            |\[(?:[^\]]+|\]\])*+\]
            |(?:--(?=$|[\x01-\x21\x7f])|\#)[^\n]*+
            |/\*(?:[^*]+|\*(?!/))*+\*/
        )
        EOF;

    protected string $identifierEscapeChar = '`';
}
