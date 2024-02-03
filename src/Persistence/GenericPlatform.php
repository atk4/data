<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms;

class GenericPlatform extends Platforms\AbstractPlatform
{
    private function createNotSupportedException(): \Exception
    {
        return DbalException::notSupported('SQL');
    }

    /**
     * @deprecated remove once DBAL 3.x support is dropped
     */
    public function getName(): string
    {
        return 'atk4_data_generic';
    }

    #[\Override]
    protected function initializeDoctrineTypeMappings(): void {}

    #[\Override]
    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getBlobTypeDeclarationSQL(array $field): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getBooleanTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getClobTypeDeclarationSQL(array $field): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getIntegerTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getCurrentDatabaseExpression(): string
    {
        throw $this->createNotSupportedException();
    }
}
