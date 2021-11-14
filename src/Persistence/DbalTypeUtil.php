<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

final class DbalTypeUtil
{
    /** @var array<string, string> */
    private static $typeAliases = [];

    public static function addTypeAlias(string $customType, string $standardDbalType): void
    {
        self::$typeAliases[$customType] = $standardDbalType;
    }

    private static function resolveType(string $type): string
    {
        return self::$typeAliases[$type] ?? $type;
    }

    public static function isBinaryType(string $type): bool
    {
        $type = self::resolveType($type);

        return in_array($type, ['binary', 'blob'], true);
    }

//    public static function isTextType(string $type): bool
//    {
//        $type = self::resolveType($type);
//
//        return in_array($type, ['string', 'text'], true);
//    }
//
//    public static function isShortCharacterType(string $type): bool
//    {
//        $type = self::resolveType($type);
//
//        return in_array($type, ['binary', 'string'], true);
//    }
//
//    public static function isLongCharacterType(string $type): bool
//    {
//        $type = self::resolveType($type);
//
//        return in_array($type, ['blob', 'text'], true);
//    }
}
