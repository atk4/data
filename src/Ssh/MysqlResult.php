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

    public function getErrorCode(): int
    {
        $res = preg_replace('~^ERROR (\d++).+$~s', '$1', $this->error);
        assert(ctype_digit($res));

        return (int) $res;
    }
}
