<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql;

use Atk4\Data\Exception;
use Atk4\Data\Field;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\SQLServer2012Platform;

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

        return hex2bin($hex);
    }

    private function binaryTypeIsEncodeNeededByPlatform(): bool
    {
        return $this->getDatabasePlatform() instanceof SQLServer2012Platform
            || $this->getDatabasePlatform() instanceof OraclePlatform;
    }

    public function typecastSaveField(Field $field, $value)
    {
        $value = parent::typecastSaveField($field, $value);

        if ($value !== null && $this->binaryTypeIsEncodeNeededByPlatform()
            && in_array($field->getTypeObject()->getName(), ['binary', 'blob'], true)) {
            $value = $this->binaryTypeValueEncode($value);
        }

        return $value;
    }

    public function typecastLoadField(Field $field, $value)
    {
        $value = parent::typecastLoadField($field, $value);

        if ($value !== null && $this->binaryTypeIsEncodeNeededByPlatform()
            && in_array($field->getTypeObject()->getName(), ['binary', 'blob'], true)) {
            $value = $this->binaryTypeValueDecode($value);
        }

        return $value;
    }
}
