<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms;
use Mvorisek\Atk4\Hintable\Phpstan\PhpstanUtil;

class GenericPlatform extends Platforms\AbstractPlatform
{
    private function createNotSupportedException(): \Exception
    {
        if (\Atk4\Data\Persistence\Sql\Connection::isComposerDbal2x()) {
            // hack for PHPStan, keep ignored error count for DBAL 2.x and DBAL 3.x the same
            if (PhpstanUtil::alwaysFalseAnalyseOnly()) {
                $connection = (new class() extends Sql\Connection {})->connection();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
                $connection->getSchemaManager();
            }
        }

        return DbalException::notSupported('SQL');
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

    public function getCurrentDatabaseExpression(): string
    {
        throw $this->createNotSupportedException();
    }
}
