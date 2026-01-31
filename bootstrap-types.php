<?php

declare(strict_types=1);

namespace Atk4\Data\Bootstrap;

use Atk4\Data\Type\LocalObjectType;
use Atk4\Data\Type\MoneyType;
use Atk4\Data\Type\Types;
use Doctrine\DBAL\Platforms\SqlitePlatform; // @phpstan-ignore class.nameCase
use Doctrine\DBAL\Platforms\SQLitePlatform as SQLitePlatform4;
use Doctrine\DBAL\Schema\SqliteSchemaManager; // @phpstan-ignore class.nameCase
use Doctrine\DBAL\Schema\SQLiteSchemaManager as SQLiteSchemaManager4;
use Doctrine\DBAL\Types as DbalTypes;

// workaround https://github.com/phpstan/phpstan/issues/14036
// remove once DBAL 3.x support is dropped
try {
    new SQLitePlatform4();
    new SQLiteSchemaManager4(); // @phpstan-ignore arguments.count
} catch (\Error $e) {
}

// force SQLitePlatform and SQLiteSchemaManager classes load as in DBAL 3.x they are named with a different case
// remove once DBAL 3.x support is dropped
try {
    new SqlitePlatform(); // @phpstan-ignore class.nameCase
    new SqliteSchemaManager(); // @phpstan-ignore class.nameCase, arguments.count
} catch (\Error $e) {
}

DbalTypes\Type::addType(Types::LOCAL_OBJECT, LocalObjectType::class);
DbalTypes\Type::addType(Types::MONEY, MoneyType::class);
