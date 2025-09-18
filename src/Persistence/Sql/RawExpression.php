<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

class RawExpression extends Expression
{
    #[\Override]
    protected function escapeStringLiteral(string $value): string
    {
        $dummyExpression = $this->expr();

        // https://github.com/php/php-src/issues/14009
        return \PHP_VERSION_ID < 8_03_08
            ? \Closure::bind(static fn () => $dummyExpression->escapeStringLiteral($value), null, parent::class)()
            : $dummyExpression->escapeStringLiteral($value);
    }

    #[\Override]
    public function render(): array
    {
        return [$this->template, $this->args['custom']];
    }
}
