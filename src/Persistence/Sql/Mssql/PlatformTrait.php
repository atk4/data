<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mssql;

use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\PlatformFixColumnCommentTypeHintTrait;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Table;

trait PlatformTrait
{
    use PlatformFixColumnCommentTypeHintTrait;

    /** @var list<string> */
    private $requireCommentHintTypes = [
        'text',
    ];

    public function getStringTypeDeclarationSQL(array $column): string
    {
        $column['length'] = ($column['length'] ?? 255) * 4;

        return Connection::isDbal3x()
            ? parent::getVarcharTypeDeclarationSQL($column) // @phpstan-ignore staticMethod.notFound
            : parent::getStringTypeDeclarationSQL($column);
    }

    /**
     * @param array<string, mixed> $column
     *
     * @deprecated remove once DBAL 3.x support is dropped
     */
    public function getVarcharTypeDeclarationSQL(array $column): string // @phpstan-ignore method.childParameterType
    {
        return $this->getStringTypeDeclarationSQL($column);
    }

    // remove once https://github.com/doctrine/dbal/pull/4987 is fixed
    #[\Override]
    public function getClobTypeDeclarationSQL(array $column): string
    {
        $res = parent::getClobTypeDeclarationSQL($column);

        return (str_starts_with($res, 'VARCHAR') ? 'N' : '') . $res;
    }

    #[\Override]
    public function getCurrentDatabaseExpression(bool $includeSchema = false): string
    {
        if ($includeSchema) {
            return 'CONCAT(DB_NAME(), \'.\', SCHEMA_NAME())';
        }

        return parent::getCurrentDatabaseExpression();
    }

    /**
     * @param string|Table $table
     */
    #[\Override]
    public function getCreateIndexSQL(Index $index, $table): string
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
    protected function getCreateColumnCommentSQL($tableName, $columnName, $comment): string
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getAddExtendedPropertySQL( // @phpstan-ignore method.internal
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
    protected function getAlterColumnCommentSQL($tableName, $columnName, $comment): string
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getUpdateExtendedPropertySQL( // @phpstan-ignore method.internal
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
    protected function getDropColumnCommentSQL($tableName, $columnName): string
    {
        if (str_contains($tableName, '.')) {
            [$schemaName, $tableName] = explode('.', $tableName);
        } else {
            $schemaName = 'dbo';
        }

        return $this->getDropExtendedPropertySQL( // @phpstan-ignore method.internal
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
