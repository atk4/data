<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\DateIntervalUnit;
use Doctrine\DBAL\Platforms\Exception\NotSupported;
use Doctrine\DBAL\Platforms\Keywords\KeywordList;
use Doctrine\DBAL\Schema\AbstractSchemaManager;
use Doctrine\DBAL\Schema\TableDiff;
use Doctrine\DBAL\TransactionIsolationLevel;

if (!Sql\Connection::isDbal3x()) {
    trait GenericPlatformTrait
    {
        #[\Override]
        public function getLocateExpression(string $string, string $substring, ?string $start = null): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getDateDiffExpression(string $date1, string $date2): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        protected function getDateArithmeticIntervalExpression(string $date, string $operator, string $interval, DateIntervalUnit $unit): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getAlterTableSQL(TableDiff $diff): array
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getListViewsSQL(string $database): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getSetTransactionIsolationSQL(TransactionIsolationLevel $level): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getDateTimeTypeDeclarationSQL(array $column): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getDateTypeDeclarationSQL(array $column): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        public function getTimeTypeDeclarationSQL(array $column): string
        {
            throw $this->createNotSupportedException();
        }

        #[\Override]
        protected function createReservedKeywordsList(): KeywordList
        {
            throw $this->createNotSupportedException();
        }

        #[\Override] // @phpstan-ignore missingType.generics
        public function createSchemaManager(Connection $connection): AbstractSchemaManager
        {
            throw $this->createNotSupportedException();
        }
    }
} else {
    trait GenericPlatformTrait {}
}

class GenericPlatform extends AbstractPlatform
{
    use GenericPlatformTrait;

    private function createNotSupportedException(): \Exception
    {
        return Sql\Connection::isDbal3x()
            ? DbalException::notSupported('SQL') // @phpstan-ignore staticMethod.notFound
            : NotSupported::new('SQL');
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
    public function getBooleanTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getIntegerTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getBigIntTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getSmallIntTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    protected function _getCommonIntegerTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getClobTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        throw $this->createNotSupportedException();
    }

    #[\Override]
    public function getCurrentDatabaseExpression(): string
    {
        throw $this->createNotSupportedException();
    }
}
