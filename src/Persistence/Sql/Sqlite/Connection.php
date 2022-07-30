<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;
use Doctrine\Common\EventManager;
use Doctrine\Common\EventSubscriber;
use Doctrine\DBAL\Event\ConnectionEventArgs;
use Doctrine\DBAL\Events;

class Connection extends BaseConnection
{
    protected string $queryClass = Query::class;

    protected static function createDbalEventManager(): EventManager
    {
        $evm = parent::createDbalEventManager();

        // setup connection to always check foreign keys
        $evm->addEventSubscriber(new class() implements EventSubscriber {
            public function getSubscribedEvents(): array
            {
                return [Events::postConnect];
            }

            public function postConnect(ConnectionEventArgs $args): void
            {
                $args->getConnection()->executeStatement('PRAGMA foreign_keys = 1');
            }
        });

        return $evm;
    }
}
