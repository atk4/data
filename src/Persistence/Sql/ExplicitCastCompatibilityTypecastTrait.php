<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

trait ExplicitCastCompatibilityTypecastTrait
{
    private function explicitCastValueGetPrefixConst(): string
    {
        return "atk4_explicit_cast\ru5f8mzx4vsm8g2c9\r";
    }

    private function explicitCastValueEncode(string $type, string $value): string
    {
        return $this->explicitCastValueGetPrefixConst() . $type . '_' . hash('crc32b', $value) . "\r" . $value;
    }

    private function explicitCastValueIsEncoded(string $value): bool
    {
        return str_starts_with($value, $this->explicitCastValueGetPrefixConst());
    }

    private function explicitCastValueDecodeType(string $value): string
    {
        if (!$this->explicitCastValueIsEncoded($value)) {
            throw new Exception('Unexpected unencoded value');
        }

        $prefixLength = strlen($this->explicitCastValueGetPrefixConst());
        $nextCrPos = strpos($value, "\r", $prefixLength);
        if ($nextCrPos !== false) {
            $nextLine = substr($value, $prefixLength, $nextCrPos - $prefixLength);
            $lastUnderscorePos = strrpos($nextLine, '_');
            if ($lastUnderscorePos !== false) {
                $resType = substr($nextLine, 0, $lastUnderscorePos);
            }
        }
        if ($nextCrPos === false || $lastUnderscorePos === false) {
            throw new Exception('Unexpected encoded value format');
        }

        return $resType;
    }

    private function explicitCastValueDecode(string $value): string
    {
        $crcPos = strlen($this->explicitCastValueGetPrefixConst()) + strlen($this->explicitCastValueDecodeType($value)) + 1;

        $resCrc = substr($value, $crcPos, 8);
        $res = substr($value, $crcPos + 9);
        if ($resCrc !== hash('crc32b', $res)) {
            throw new Exception('Unexpected encoded value crc');
        }

        if ($this->explicitCastValueIsEncoded($res)) {
            throw new Exception('Unexpected double encoded value');
        }

        return $res;
    }

    private function explicitCastIsEncodeNeeded(string $type): bool
    {
        $platform = $this->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            if ($type === 'json') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param scalar $value
     */
    private function explicitCastIsDecodeNeeded(string $type, $value): bool
    {
        if ($this->explicitCastIsEncodeNeeded($type)) {
            if ($this->explicitCastValueIsEncoded($value)) {
                return true;
            }
        }

        return false;
    }
}
