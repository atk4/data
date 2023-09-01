<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;

class Connection extends BaseConnection
{
    protected string $expressionClass = Expression::class;
    protected string $queryClass = Query::class;

    protected static function createDbalConfiguration(): Configuration
    {
        $configuration = parent::createDbalConfiguration();

        // setup connection globalization to use standard datetime format incl. microseconds support
        // and make comparison of character types case insensitive
        // based on https://github.com/doctrine/dbal/blob/3.6.5/src/Driver/OCI8/Middleware/InitializeSession.php
        $initializeSessionMiddleware = new class() implements Middleware {
            public function wrap(Driver $driver): Driver
            {
                return new class($driver) extends AbstractDriverMiddleware {
                    public function connect(
                        #[\SensitiveParameter]
                        array $params
                    ): DriverConnection {
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
        };

        $configuration->setMiddlewares([...$configuration->getMiddlewares(), $initializeSessionMiddleware]);

        return $configuration;
    }

    public function lastInsertId(string $sequence = null): string
    {
        if ($sequence) {
            return $this->dsql()->field($this->expr('{{}}.CURRVAL', [$sequence]))->getOne();
        }

        return parent::lastInsertId($sequence);
    }
}
