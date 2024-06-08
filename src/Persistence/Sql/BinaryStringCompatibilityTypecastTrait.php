<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\OraclePlatform;

trait BinaryStringCompatibilityTypecastTrait
{
    private function binaryStringGetPrefixConst(): string
    {
        return "atk4_binary\ru5f8mzx4vsm8g2c9\r";
    }

    private function binaryStringEncode(string $value): string
    {
        $hex = bin2hex($value);

        return $this->binaryStringGetPrefixConst() . hash('crc32b', $hex) . $hex;
    }

    private function binaryStringIsEncoded(string $value): bool
    {
        return str_starts_with($value, $this->binaryStringGetPrefixConst());
    }

    private function binaryStringDecode(string $value): string
    {
        if (!$this->binaryStringIsEncoded($value)) {
            throw new Exception('Unexpected unencoded binary value');
        }

        $prefixLength = strlen($this->binaryStringGetPrefixConst());
        $hexCrc = substr($value, $prefixLength, 8);
        $hex = substr($value, $prefixLength + 8);
        if ((strlen($hex) % 2) !== 0 || $hexCrc !== hash('crc32b', $hex)) {
            throw new Exception('Unexpected binary value crc');
        }

        $res = hex2bin($hex);
        if ($this->binaryStringIsEncoded($res)) {
            throw new Exception('Unexpected double encoded binary value');
        }

        return $res;
    }

    private function binaryStringIsEncodeNeeded(string $type): bool
    {
        return $this->getDatabasePlatform() instanceof OraclePlatform
            && in_array($type, ['binary', 'blob'], true);
    }
}
