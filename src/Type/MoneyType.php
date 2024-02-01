<?php

declare(strict_types=1);

namespace Atk4\Data\Type;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types as DbalTypes;

class MoneyType extends DbalTypes\Type
{
    #[\Override]
    public function getName(): string
    {
        return Types::MONEY;
    }

    #[\Override]
    public function getSQLDeclaration(array $fieldDeclaration, AbstractPlatform $platform): string
    {
        return DbalTypes\Type::getType(DbalTypes\Types::FLOAT)->getSQLDeclaration($fieldDeclaration, $platform);
    }

    #[\Override]
    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?float
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return round((float) $value, 4);
    }

    #[\Override]
    public function convertToPHPValue($value, AbstractPlatform $platform): ?float
    {
        return $this->convertToDatabaseValue($value, $platform);
    }

    #[\Override]
    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
