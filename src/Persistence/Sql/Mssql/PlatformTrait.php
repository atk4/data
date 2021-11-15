<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

trait PlatformTrait
{
    // SQL Server database requires explicit conversion when using binary column,
    // workaround by using a standard non-binary column with custom encoding/typecast

    protected function getBinaryTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $this->getVarcharTypeDeclarationSQLSnippet($length, $fixed);
    }

    public function getBlobTypeDeclarationSQL(array $column)
    {
        return $this->getClobTypeDeclarationSQL($column);
    }

    // remove once https://github.com/doctrine/dbal/pull/4987 is fixed
    // and also $this->markDoctrineTypeCommented('text') below
    public function getClobTypeDeclarationSQL(array $column)
    {
        $res = parent::getClobTypeDeclarationSQL($column);

        return (str_starts_with($res, 'VARCHAR') ? 'N' : '') . $res;
    }

    protected function initializeCommentedDoctrineTypes()
    {
        parent::initializeCommentedDoctrineTypes();

        $this->markDoctrineTypeCommented('binary');
        $this->markDoctrineTypeCommented('blob');
        $this->markDoctrineTypeCommented('text');
    }

    // SQL Server DBAL platform has buggy identifier escaping, fix until fixed officially, see:
    // https://github.com/doctrine/dbal/pull/4360

    protected function getCreateColumnCommentSQL($tableName, $columnName, $comment)
    {
        if (strpos($tableName, '.') !== false) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = $this->getDefaultSchemaName();
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
        if (strpos($tableName, '.') !== false) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = $this->getDefaultSchemaName();
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
        if (strpos($tableName, '.') !== false) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = $this->getDefaultSchemaName();
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
        if (strpos($tableName, '.') !== false) {
            [$schemaName, $tableName] = explode('.', $tableName, 2);
        } else {
            $schemaName = $this->getDefaultSchemaName();
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
