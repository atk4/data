<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

class MysqlResult
{
    public ?string $error;
    public int $affectedRows = 0;
    /** @var list<array<string|int, scalar>> */
    public array $rows = [];
    public float $elapsed = -1.0;
}
