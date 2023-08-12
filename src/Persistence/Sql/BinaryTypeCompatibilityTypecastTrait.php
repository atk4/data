<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Exception;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLServerPlatform;

trait BinaryTypeCompatibilityTypecastTrait
{
    private function binaryTypeValueGetPrefixConst(): string
    {
        return 'atk__binary__u5f8mzx4vsm8g2c9__';
    }

    private function binaryTypeValueEncode(string $value): string
    {
        $hex = bin2hex($value);

        return $this->binaryTypeValueGetPrefixConst() . hash('crc32b', $hex) . $hex;
    }

    private function binaryTypeValueIsEncoded(string $value): bool
    {
        return str_starts_with($value, $this->binaryTypeValueGetPrefixConst());
    }

    private function binaryTypeValueDecode(string $value): string
    {
        if (!$this->binaryTypeValueIsEncoded($value)) {
            throw new Exception('Unexpected unencoded binary value');
        }

        $hexCrc = substr($value, strlen($this->binaryTypeValueGetPrefixConst()), 8);
        $hex = substr($value, strlen($this->binaryTypeValueGetPrefixConst()) + 8);
        if ((strlen($hex) % 2) !== 0 || $hexCrc !== hash('crc32b', $hex)) {
            throw new Exception('Unexpected binary value crc');
        }

        $res = hex2bin($hex);
        if ($this->binaryTypeValueIsEncoded($res)) {
            throw new Exception('Unexpected double encoded binary value');
        }

        return $res;
    }

    private function binaryTypeIsEncodeNeeded(string $type): bool
    {
        // binary values for PostgreSQL and MSSQL databases are stored natively, but we need
        // to encode first to hold the binary type info for PDO parameter type binding

        $platform = $this->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform
            || $platform instanceof SQLServerPlatform
            || $platform instanceof OraclePlatform
        ) {
            if (in_array($type, ['binary', 'blob'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param scalar $value
     */
    private function binaryTypeIsDecodeNeeded(string $type, $value): bool
    {
        if ($this->binaryTypeIsEncodeNeeded($type)) {
            // always decode for Oracle platform to assert the value is always encoded,
            // on other platforms, binary values are stored natively
            if ($this->getDatabasePlatform() instanceof OraclePlatform || $this->binaryTypeValueIsEncoded($value)) {
                return true;
            }
        }

        return false;
    }
}
