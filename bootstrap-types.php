<?php

declare(strict_types=1);

namespace Atk4\Data\Bootstrap;

use Atk4\Data\Type\LocalObjectType;
use Atk4\Data\Type\MoneyType;
use Atk4\Data\Type\Types;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\SQLiteSchemaManager;
use Doctrine\DBAL\Types as DbalTypes;

// force SQLitePlatform and SQLiteSchemaManager classes load as in DBAL 3.x they are named with a different case
// remove once DBAL 3.x support is dropped
(static function () {
    try {
        foreach ([
            str_replace('SQLite', 'Sqlite', SQLitePlatform::class),
            str_replace('SQLite', 'Sqlite', SQLiteSchemaManager::class),
        ] as $class) {
            new $class();
        }
    } catch (\Error $e) {
    }
})();

DbalTypes\Type::addType(Types::LOCAL_OBJECT, LocalObjectType::class);
DbalTypes\Type::addType(Types::MONEY, MoneyType::class);
