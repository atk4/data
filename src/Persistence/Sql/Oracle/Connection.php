<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\Event\Listeners\OracleSessionInit;

class Connection extends BaseConnection
{
    protected $query_class = Query::class;

    protected static function createDbalEventManager(): EventManager
    {
        $evm = new EventManager();

        // setup connection globalization to use standard datetime format incl. microseconds support
        $dateFormat = 'YYYY-MM-DD';
        $timeFormat = 'HH24:MI:SS.FF6';
        $tzFormat = 'TZH:TZM';
        $evm->addEventSubscriber(new OracleSessionInit([
            'NLS_DATE_FORMAT' => $dateFormat,
            'NLS_TIME_FORMAT' => $timeFormat,
            'NLS_TIMESTAMP_FORMAT' => $dateFormat . ' ' . $timeFormat,
            'NLS_TIME_TZ_FORMAT' => $timeFormat . ' ' . $tzFormat,
            'NLS_TIMESTAMP_TZ_FORMAT' => $dateFormat . ' ' . $timeFormat . ' ' . $tzFormat,
        ]));

        return $evm;
    }

    /**
     * Return last inserted ID value.
     *
     * Drivers like PostgreSQL need to receive sequence name to get ID because PDO doesn't support this method.
     */
    public function lastInsertId(string $sequence = null): string
    {
        if ($sequence) {
            /** @var AbstractQuery */
            $query = $this->dsql()->mode('seq_currval');

            return $query->sequence($sequence)->getOne();
        }

        // fallback
        return parent::lastInsertId($sequence);
    }
}
