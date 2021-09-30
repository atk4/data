<?php

declare(strict_types=1);

namespace Atk4\Data\Types;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types as DbalTypes;

// TODO types to migration to DBAL, might be removed later

final class Types
{
    public const MONEY = 'money';
    public const PASSWORD = 'password';
}

class MoneyType extends DbalTypes\Type
{
    public function getName(): string
    {
        return Types::MONEY;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return DbalTypes\Type::getType(DbalTypes\Types::FLOAT)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

class PasswordType extends DbalTypes\Type
{
    public function getName(): string
    {
        return Types::PASSWORD;
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return DbalTypes\Type::getType(DbalTypes\Types::STRING)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

DbalTypes\Type::addType(Types::MONEY, MoneyType::class);
DbalTypes\Type::addType(Types::PASSWORD, PasswordType::class);
