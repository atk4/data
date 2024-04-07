<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Index;

trait PlatformTrait
{
    #[\Override]
    public function getVarcharTypeDeclarationSQL(array $column)
    {
        $column['length'] = ($column['length'] ?? 255) * 4;

        return parent::getVarcharTypeDeclarationSQL($column);
    }

    // remove once https://github.com/doctrine/dbal/pull/4987 is fixed
    // and also $this->markDoctrineTypeCommented('text') below
    #[\Override]
    public function getClobTypeDeclarationSQL(array $column)
    {
        $res = parent::getClobTypeDeclarationSQL($column);

        return (str_starts_with($res, 'VARCHAR') ? 'N' : '') . $res;
    }

    // TODO test DBAL DB diff for each supported Field type
    // then fix using https://github.com/doctrine/dbal/issues/5194#issuecomment-1018790220
    /* protected function initializeCommentedDoctrineTypes()
    {
        parent::initializeCommentedDoctrineTypes();

        $this->markDoctrineTypeCommented('text');
    } */

    #[\Override]
    public function getCurrentDatabaseExpression(bool $includeSchema = false): string
    {
        if ($includeSchema) {
            return 'CONCAT(DB_NAME(), \'.\', SCHEMA_NAME())';
        }

        return parent::getCurrentDatabaseExpression();
    }

    #[\Override]
    public function getCreateIndexSQL(Index $index, $table)
    {
        // workaround https://github.com/doctrine/dbal/issues/5507
        // no side effect on DBAL index list observed, but multiple null values cannot be inserted
        // the only, very complex, solution would be using intermediate view
        // SQL Server should be fixed to allow FK creation when there is an unique index
        // with "WHERE xxx IS NOT NULL" as FK does not restrict NULL values anyway
        return $index->hasFlag('atk4-not-null')
            ? AbstractPlatform::getCreateIndexSQL($index, $table)
            : parent::getCreateIndexSQL($index, $table);
    }

    // SQL Server DBAL platform has buggy identifier escaping, fix until fixed officially, see:
    // https://github.com/doctrine/dbal/pull/6353

    private function unquoteSingleIdentifier(string $possiblyQuotedName): string
    {
        return str_starts_with($possiblyQuotedName, '[') && str_ends_with($possiblyQuotedName, ']')
            ? substr($possiblyQuotedName, 1, -1)
            : $possiblyQuotedName;
    }

    #[\Override]
    protected function getCreateColumnCommentSQL($tableName, $columnName, $comment)
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getAddExtendedPropertySQL(
            'MS_Description',
            $comment,
            'SCHEMA',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($schemaName)),
            'TABLE',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($tableName)),
            'COLUMN',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($columnName)),
        );
    }

    #[\Override]
    protected function getAlterColumnCommentSQL($tableName, $columnName, $comment)
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getUpdateExtendedPropertySQL(
            'MS_Description',
            $comment,
            'SCHEMA',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($schemaName)),
            'TABLE',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($tableName)),
            'COLUMN',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($columnName)),
        );
    }

    #[\Override]
    protected function getDropColumnCommentSQL($tableName, $columnName)
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getDropExtendedPropertySQL(
            'MS_Description',
            'SCHEMA',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($schemaName)),
            'TABLE',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($tableName)),
            'COLUMN',
            $this->quoteStringLiteral($this->unquoteSingleIdentifier($columnName)),
        );
    }
}
