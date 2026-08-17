<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Schema\OracleConnectionStats;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\OCI8\Exception\ConnectionFailed;

/**
 * Retries transient Oracle listener failures during connection establishment.
 *
 * Oracle may temporarily return ORA-12516 when the listener has not yet
 * released a handler after a connection has been closed. Retrying after
 * a short delay allows the listener to refresh its handler state.
 */
class RetryConnectionMiddleware implements Middleware
{
    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            // ORA-12516: TNS:listener could not find available handler
            // with matching protocol stack.
            private const RETRY_ERROR_CODES = [12516];

            private const RETRY_COUNT = 8;

            private const RETRY_INTERVAL_BASE_MS = 10;

            private const RETRY_INTERVAL_MAX_MS = 1000;

            #[\Override]
            public function connect(
                #[\SensitiveParameter]
                array $params
            ): Connection {
                $start = hrtime(true);

                $connection = null;
                $attempts = 0;

                for (;;) {
                    ++$attempts;

                    try {
                        $connection = parent::connect($params);

                        break;
                    } catch (ConnectionFailed $e) { // @phpstan-ignore catch.internalClass
                        if (
                            !in_array($e->getCode(), self::RETRY_ERROR_CODES, true)
                            || $attempts > self::RETRY_COUNT
                        ) {
                            throw $e;
                        }

                        $retryNumber = $attempts - 1;

                        $timeoutMs = min(
                            self::RETRY_INTERVAL_BASE_MS * (2 ** $retryNumber),
                            self::RETRY_INTERVAL_MAX_MS
                        );

                        usleep($timeoutMs * 1000);
                    }
                }

                OracleConnectionStats::record(
                    hrtime(true) - $start,
                    $attempts
                );

                return $connection;
            }
        };
    }
}
