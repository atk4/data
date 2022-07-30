<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;

class Connection extends BaseConnection
{
    protected string $queryClass = Query::class;

    protected static function createDbalEventManager(): EventManager
    {
        $evm = parent::createDbalEventManager();

        // setup connection globalization to use standard datetime format incl. microseconds support
        // and make comparison of character types case insensitive
        $dateFormat = 'YYYY-MM-DD';
        $timeFormat = 'HH24:MI:SS.FF6';
        $tzFormat = 'TZH:TZM';
        $evm->addEventSubscriber(new OracleSessionInit([
            'NLS_DATE_FORMAT' => $dateFormat,
            'NLS_TIME_FORMAT' => $timeFormat,
            'NLS_TIMESTAMP_FORMAT' => $dateFormat . ' ' . $timeFormat,
            'NLS_TIME_TZ_FORMAT' => $timeFormat . ' ' . $tzFormat,
            'NLS_TIMESTAMP_TZ_FORMAT' => $dateFormat . ' ' . $timeFormat . ' ' . $tzFormat,
            'NLS_COMP' => 'LINGUISTIC',
            'NLS_SORT' => 'BINARY_CI',
        ]));

        return $evm;
    }

    public function lastInsertId(string $sequence = null): string
    {
        if ($sequence) {
            return $this->dsql()->field($this->expr('{{}}.CURRVAL', [$sequence]))->getOne();
        }

        return parent::lastInsertId($sequence);
    }
}
