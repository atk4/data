<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

class RawExpression extends Expression
{
    #[\Override]
    protected function escapeStringLiteral(string $value): string
    {
        $dummyExpression = $this->connection->expr();

        // Closure rebind should not be needed
        // https://github.com/php/php-src/issues/14009
        return \Closure::bind(static fn () => $dummyExpression->escapeStringLiteral($value), null, parent::class)();
    }

    #[\Override]
    public function render(): array
    {
        return [$this->template, $this->args['custom']];
    }
}
