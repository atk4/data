<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

/**
 * Setup connection globalization to use standard datetime format incl. microseconds support
 * and make comparison of character types case insensitive.
 *
 * Based on https://github.com/doctrine/dbal/blob/3.6.5/src/Driver/OCI8/Middleware/InitializeSession.php
 */
class InitializeSessionMiddleware implements Middleware
{
    #[\Override]
    public function wrap(Driver $driver): Driver
    {
        return new class($driver) extends AbstractDriverMiddleware {
            #[\Override]
            public function connect(
                #[\SensitiveParameter]
                array $params
            ): Connection {
                $connection = parent::connect($params);

                $dateFormat = 'YYYY-MM-DD';
                $timeFormat = 'HH24:MI:SS.FF6';
                $tzFormat = 'TZH:TZM';

                $vars = [];
                foreach ([
                    'NLS_DATE_FORMAT' => $dateFormat,
                    'NLS_TIME_FORMAT' => $timeFormat,
                    'NLS_TIMESTAMP_FORMAT' => $dateFormat . ' ' . $timeFormat,
                    'NLS_TIME_TZ_FORMAT' => $timeFormat . ' ' . $tzFormat,
                    'NLS_TIMESTAMP_TZ_FORMAT' => $dateFormat . ' ' . $timeFormat . ' ' . $tzFormat,
                    'NLS_NUMERIC_CHARACTERS' => '.,',
                    'NLS_COMP' => 'LINGUISTIC',
                    'NLS_SORT' => 'BINARY_CI',
                ] as $k => $v) {
                    $vars[] = $k . " = '" . $v . "'";
                }

                $connection->exec('ALTER SESSION SET ' . implode(' ', $vars));

                return $connection;
            }
        };
    }
}
