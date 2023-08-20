<?php

declare(strict_types=1);

namespace Atk4\Data\Bootstrap;

use Atk4\Data\Type\LocalObjectType;
use Atk4\Data\Type\MoneyType;
use Atk4\Data\Type\Types;
use Doctrine\DBAL\Platforms\SqlitePlatform;
use Doctrine\DBAL\Types as DbalTypes;

// force Doctrine\DBAL\Platforms\SQLitePlatform class load as in DBAL 3.x it is named with a different case
// remove once DBAL 3.x support is dropped
new SqlitePlatform();

DbalTypes\Type::addType(Types::LOCAL_OBJECT, LocalObjectType::class);
DbalTypes\Type::addType(Types::MONEY, MoneyType::class);
