<?php

declare(strict_types=1);

namespace atk4\data\Persistence;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Platforms;

class GenericPlatform extends Platforms\AbstractPlatform
{
    private function createNotSupportedException(): DBALException
    {
        return DBALException::notSupported('SQL');
    }

    public function getName(): string
    {
        return 'atk4_data_generic';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
    }

    protected function _getCommonIntegerTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getBigIntTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getBlobTypeDeclarationSQL(array $field): string
    {
        throw $this->createNotSupportedException();
    }

    public function getBooleanTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getClobTypeDeclarationSQL(array $field): string
    {
        throw $this->createNotSupportedException();
    }

    public function getIntegerTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }

    public function getSmallIntTypeDeclarationSQL(array $columnDef): string
    {
        throw $this->createNotSupportedException();
    }
}
