<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\OCI8\Exception\ConnectionFailed;

/**
 * Oracle listener may temporarily return ORA-12516 when connecting and when the listener has not yet
 * fully released previous connections.
 *
 * Needed for reliable connecting for Oracle v23.4 or higher (at least v23.26.3).
 */
class RetryListenerConnectionMiddleware implements Middleware
{
    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            private const RETRY_COUNT = 8;
            private const RETRY_INTERVAL_BASE_MS = 10;
            private const RETRY_INTERVAL_MAX_MS = 1000;

            #[\Override]
            public function connect(
                #[\SensitiveParameter]
                array $params
            ): Connection {
                for ($attempt = 0;; ++$attempt) {
                    try {
                        return parent::connect($params);
                    } catch (ConnectionFailed $e) { // @phpstan-ignore catch.internalClass
                        if ($e->getCode() !== 12516 || $attempt >= self::RETRY_COUNT) {
                            throw $e;
                        }

                        $timeoutMs = min(
                            self::RETRY_INTERVAL_BASE_MS * (2 ** $attempt),
                            self::RETRY_INTERVAL_MAX_MS
                        );

                        usleep($timeoutMs * 1000);
                    }
                }
            }
        };
    }
}
