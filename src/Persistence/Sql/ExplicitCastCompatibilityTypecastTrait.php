<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

trait ExplicitCastCompatibilityTypecastTrait
{
    private function explicitCastGetPrefixConst(): string
    {
        return "atk4_explicit_cast\ru5f8mzx4vsm8g2c9\r";
    }

    private function explicitCastEncode(string $type, string $value): string
    {
        return $this->explicitCastGetPrefixConst() . $type . "\r" . $value;
    }

    private function explicitCastIsEncoded(string $value): bool
    {
        return str_starts_with($value, $this->explicitCastGetPrefixConst());
    }

    private function explicitCastDecodeType(string $value): string
    {
        if (!$this->explicitCastIsEncoded($value)) {
            throw new Exception('Unexpected unencoded value');
        }

        $prefixLength = strlen($this->explicitCastGetPrefixConst());
        $nextCrPos = strpos($value, "\r", $prefixLength);
        if ($nextCrPos === false) {
            throw new Exception('Unexpected encoded value format');
        }

        $type = substr($value, $prefixLength, $nextCrPos - $prefixLength);

        return $type;
    }

    private function explicitCastDecode(string $value): string
    {
        $resPos = strlen($this->explicitCastGetPrefixConst()) + strlen($this->explicitCastDecodeType($value)) + 1;
        $res = substr($value, $resPos);

        if ($this->explicitCastIsEncoded($res)) {
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
            if ($this->explicitCastIsEncoded($value)) {
                return true;
            }
        }

        return false;
    }
}
