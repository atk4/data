<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;

trait JsonTypeCompatibilityTypecastTrait
{
    private function jsonTypeValueGetPrefixConst(): string
    {
        return "atk4_json\ru5f8mzx4vsm8g2c9\r";
    }

    private function jsonTypeValueEncode(string $value): string
    {
        return $this->jsonTypeValueGetPrefixConst() . hash('crc32b', $value) . $value;
    }

    private function jsonTypeValueIsEncoded(string $value): bool
    {
        return str_starts_with($value, $this->jsonTypeValueGetPrefixConst());
    }

    private function jsonTypeValueDecode(string $value): string
    {
        if (!$this->jsonTypeValueIsEncoded($value)) {
            throw new Exception('Unexpected unencoded json value');
        }

        $resCrc = substr($value, strlen($this->jsonTypeValueGetPrefixConst()), 8);
        $res = substr($value, strlen($this->jsonTypeValueGetPrefixConst()) + 8);
        if ($resCrc !== hash('crc32b', $res)) {
            throw new Exception('Unexpected json value crc');
        }

        if ($this->jsonTypeValueIsEncoded($res)) {
            throw new Exception('Unexpected double encoded json value');
        }

        return $res;
    }

    private function jsonTypeIsEncodeNeeded(string $type): bool
    {
        // json values for PostgreSQL database are stored natively, but we need
        // to encode first to hold the json type info for PDO parameter type binding

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
    private function jsonTypeIsDecodeNeeded(string $type, $value): bool
    {
        if ($this->jsonTypeIsEncodeNeeded($type)) {
            if ($this->jsonTypeValueIsEncoded($value)) {
                return true;
            }
        }

        return false;
    }
}
