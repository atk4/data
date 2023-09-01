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

    public function convertToDatabaseValue($value, AbstractPlatform $platform): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (string) round((float) $value, 4);
    }

    public function convertToPHPValue($value, AbstractPlatform $platform): ?float
    {
        $v = $this->convertToDatabaseValue($value, $platform);

        return $v === null ? null : (float) $v;
    }

    public function requiresSQLCommentHint(AbstractPlatform $platform): bool
    {
        return true;
    }
}
