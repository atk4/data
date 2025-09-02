<?php

declare(strict_types=1);

namespace Atk4\Data\Ssh;

use Atk4\Core\Phpunit\TestCase as CoreTestCase;
use Atk4\Data\Exception;

/**
 * @method positive-int getCode()
 */
class MysqlException extends Exception
{
    public float $elapsed;

    public function __construct(string $message, int $code, ?\Throwable $previous = null)
    {
        assert($code > 0);

        parent::__construct($message, $code, $previous);

        // remove MysqlConnection object from stack trace to prevent "Too many connections"
        $this->releaseObjectsFromExceptionTrace($this);
    }

    private function releaseObjectsFromExceptionTrace(\Throwable $e): void
    {
        $coreTestCase = new class('') extends CoreTestCase {}; // @phpstan-ignore method.internal, argument.type
        \Closure::bind(static fn () => $coreTestCase->releaseObjectsFromExceptionTrace($e), null, CoreTestCase::class)();
    }
}
