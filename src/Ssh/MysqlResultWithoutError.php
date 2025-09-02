<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

class MysqlResultWithoutError extends MysqlResult
{
    /** @var never */
    public ?string $error; // @phpstan-ignore property.phpDocType

    public function __construct(MysqlResult $res)
    {
        assert($res->error === null);
        $this->affectedRows = $res->affectedRows;
        $this->rows = $res->rows;
        $this->elapsed = $res->elapsed;
    }

    /**
     * @return never
     */
    #[\Override]
    public function getErrorCode(): int
    {
        return null; // @phpstan-ignore return.never
    }
}
