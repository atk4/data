<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Index;

trait PlatformTrait
{
    public function getVarcharTypeDeclarationSQL(array $column)
    {
        $column['length'] = ($column['length'] ?? 255) * 4;

        return parent::getVarcharTypeDeclarationSQL($column);
    }

    // remove once https://github.com/doctrine/dbal/pull/4987 is fixed
    // and also $this->markDoctrineTypeCommented('text') below
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

    public function getCurrentDatabaseExpression(bool $includeSchema = false): string
    {
        if ($includeSchema) {
            return 'CONCAT(DB_NAME(), \'.\', SCHEMA_NAME())';
        }

        return parent::getCurrentDatabaseExpression();
    }

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
    // https://github.com/doctrine/dbal/pull/4360

    protected function getCreateColumnCommentSQL($tableName, $columnName, $comment)
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getAddExtendedPropertySQL(
            'MS_Description',
            (string) $comment,
            'SCHEMA',
            $schemaName,
            'TABLE',
            $tableName,
            'COLUMN',
            $columnName
        );
    }

    protected function getAlterColumnCommentSQL($tableName, $columnName, $comment)
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getUpdateExtendedPropertySQL(
            'MS_Description',
            (string) $comment,
            'SCHEMA',
            $schemaName,
            'TABLE',
            $tableName,
            'COLUMN',
            $columnName
        );
    }

    protected function getDropColumnCommentSQL($tableName, $columnName)
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getDropExtendedPropertySQL(
            'MS_Description',
            'SCHEMA',
            $schemaName,
            'TABLE',
            $tableName,
            'COLUMN',
            $columnName
        );
    }

    private function quoteSingleIdentifierAsStringLiteral(string $levelName): string
    {
        return $this->quoteStringLiteral(preg_replace('~^\[|\]$~s', '', $levelName));
    }

    public function getAddExtendedPropertySQL(
        $name,
        $value = null,
        $level0Type = null,
        $level0Name = null,
        $level1Type = null,
        $level1Name = null,
        $level2Type = null,
        $level2Name = null
    ) {
        return 'EXEC sp_addextendedproperty'
            . ' N' . $this->quoteStringLiteral($name) . ', N' . $this->quoteStringLiteral((string) $value)
            . ', N' . $this->quoteStringLiteral((string) $level0Type)
            . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level0Name)
            . ', N' . $this->quoteStringLiteral((string) $level1Type)
            . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level1Name)
            . (
                $level2Type !== null || $level2Name !== null
                ? ', N' . $this->quoteStringLiteral((string) $level2Type)
                  . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level2Name)
                : ''
            );
    }

    public function getDropExtendedPropertySQL(
        $name,
        $level0Type = null,
        $level0Name = null,
        $level1Type = null,
        $level1Name = null,
        $level2Type = null,
        $level2Name = null
    ) {
        return 'EXEC sp_dropextendedproperty'
            . ' N' . $this->quoteStringLiteral($name)
            . ', N' . $this->quoteStringLiteral((string) $level0Type)
            . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level0Name)
            . ', N' . $this->quoteStringLiteral((string) $level1Type)
            . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level1Name)
            . (
                $level2Type !== null || $level2Name !== null
                ? ', N' . $this->quoteStringLiteral((string) $level2Type)
                  . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level2Name)
                : ''
            );
    }

    public function getUpdateExtendedPropertySQL(
        $name,
        $value = null,
        $level0Type = null,
        $level0Name = null,
        $level1Type = null,
        $level1Name = null,
        $level2Type = null,
        $level2Name = null
    ) {
        return 'EXEC sp_updateextendedproperty'
            . ' N' . $this->quoteStringLiteral($name) . ', N' . $this->quoteStringLiteral((string) $value)
            . ', N' . $this->quoteStringLiteral((string) $level0Type)
            . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level0Name)
            . ', N' . $this->quoteStringLiteral((string) $level1Type)
            . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level1Name)
            . (
                $level2Type !== null || $level2Name !== null
                ? ', N' . $this->quoteStringLiteral((string) $level2Type)
                  . ', ' . $this->quoteSingleIdentifierAsStringLiteral((string) $level2Name)
                : ''
            );
    }

    protected function getCommentOnTableSQL(string $tableName, ?string $comment): string
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getAddExtendedPropertySQL(
            'MS_Description',
            (string) $comment,
            'SCHEMA',
            $schemaName,
            'TABLE',
            $tableName
        );
    }
}
