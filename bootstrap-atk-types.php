<?php

declare(strict_types=1);

namespace atk4\data;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Type;

// TODO for types to DBAL migration, might be removed later

class AtkTypeMoney extends Type
{
    public function getName(): string
    {
        return 'money';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType(Type::FLOAT)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

class AtkTypePassword extends Type
{
    public function getName(): string
    {
        return 'password';
    }

    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return Type::getType(Type::STRING)->getSQLDeclaration($fieldDeclaration, $platform);
    }
}

Type::addType('money', AtkTypeMoney::class);
Type::addType('password', AtkTypePassword::class);
