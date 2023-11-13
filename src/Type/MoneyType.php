<?php

declare(strict_types=1);

namespace Atk4\Data\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types as DbalTypes;

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

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?float
    {
        return $this->convertToDatabaseValue($value, $platform);
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
