<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Atk4\Data\Persistence\Sql\Expression as BaseExpression;

class Expression extends BaseExpression
{
    use ExpressionTrait;

    public const QUOTED_TOKEN_REGEX = <<<'EOF'
        (?:(?sx)
            '(?:[^']+|'')*+'
            |"(?:[^"]+|"")*+"
            |`(?:[^`]+|``)*+`
            |\[(?:[^\]]+|\]\])*+\]
            |(?:--|\#)[^\r\n]*+
            |/\*(?:[^*]+|\*(?!/))*+\*/
        )
        EOF;

    protected string $identifierEscapeChar = '"';
}
