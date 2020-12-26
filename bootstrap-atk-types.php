<?php

declare(strict_types=1);

namespace Atk4\Data;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;

// TODO for types to DBAL migration, might be removed later

final class AtkTypes
{
    public const MONEY = 'money';
    public const PASSWORD = 'password';
}

class AtkTypeMoney extends Type
{
    public function getName(): string
    {
        return AtkTypes::MONEY;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType(Types::FLOAT)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

class AtkTypePassword extends Type
{
    public function getName(): string
    {
        return AtkTypes::PASSWORD;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType(Types::STRING)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

Type::addType(AtkTypes::MONEY, AtkTypeMoney::class);
Type::addType(AtkTypes::PASSWORD, AtkTypePassword::class);
