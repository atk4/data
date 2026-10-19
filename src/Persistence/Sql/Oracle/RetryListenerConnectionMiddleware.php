<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Exception as DbalDriverException;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

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
            private const RETRY_MAX_SECONDS = 10.0;
            private const RETRY_INTERVAL_SECONDS = 0.02;

            #[\Override]
            public function connect(
                #[\SensitiveParameter]
                array $params
            ): Connection {
                $sleepSecondsSum = 0;
                while (true) {
                    try {
                        return parent::connect($params);
                        // @codeCoverageIgnoreStart
                    } catch (DbalDriverException $e) {
                        $sleepSeconds = self::RETRY_INTERVAL_SECONDS;
                        $sleepSecondsSum += $sleepSeconds;

                        if ($e->getCode() !== 12516 || $sleepSecondsSum > self::RETRY_MAX_SECONDS) {
                            throw $e;
                        }

                        usleep((int) round($sleepSeconds * 1_000_000));
                        // @codeCoverageIgnoreEnd
                    }
                }
            }
        };
    }
}
