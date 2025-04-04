<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\PlatformFixColumnCommentTypeHintTrait;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\TableDiff;

trait PlatformTrait
{
    // remove (and related property) once https://github.com/doctrine/dbal/pull/6411 is merged and released
    use PlatformFixColumnCommentTypeHintTrait;
    use PlatformTraitBackport5517 {
        PlatformTraitBackport5517::getListTableConstraintsSQL as private __getListTableConstraintsSQL;
        PlatformTraitBackport5517::getListTableColumnsSQL as private __getListTableColumnsSQL;
        PlatformTraitBackport5517::getListTableIndexesSQL as private __getListTableIndexesSQL;
        PlatformTraitBackport5517::getListTableForeignKeysSQL as private __getListTableForeignKeysSQL;
    }

    /** @var list<string> */
    private $requireCommentHintTypes = [
        'bigint',
    ];

    public function __construct()
    {
        $this->disableSchemaEmulation(); // @phpstan-ignore method.deprecated
    }

    #[\Override]
    public function getIdentifierQuoteCharacter(): string
    {
        return '`';
    }

    public function supportsForeignKeyConstraints(): bool
    {
        // backport https://github.com/doctrine/dbal/pull/5427, remove once DBAL 3.3.x support is dropped
        return true;
    }

    protected function getPreAlterTableIndexForeignKeySQL(TableDiff $diff): array
    {
        // https://github.com/doctrine/dbal/pull/5486
        return [];
    }

    // fix quoted table name support
    // TODO submit a PR with fixed SQLitePlatform to DBAL

    private function unquoteTableIdentifier(string $tableName): string
    {
        return (new Identifier($tableName))->getName();
    }

    public function getListTableConstraintsSQL($table)
    {
        return $this->__getListTableConstraintsSQL($this->unquoteTableIdentifier($table));
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        return $this->__getListTableColumnsSQL($this->unquoteTableIdentifier($table), $database);
    }

    public function getListTableIndexesSQL($table, $database = null)
    {
        return $this->__getListTableIndexesSQL($this->unquoteTableIdentifier($table), $database);
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return $this->__getListTableForeignKeysSQL($this->unquoteTableIdentifier($table), $database);
    }

    #[\Override]
    public function getAlterTableSQL(TableDiff $diff): array
    {
        // fix https://github.com/doctrine/dbal/pull/5501
        $diff = clone $diff;
        $diff->fromTable = clone $diff->fromTable;
        foreach ($diff->fromTable->getForeignKeys() as $foreignKey) {
            \Closure::bind(static function () use ($foreignKey) {
                $foreignKey->_localColumnNames = $foreignKey->createIdentifierMap($foreignKey->getUnquotedLocalColumns());
            }, null, ForeignKeyConstraint::class)();
        }

        // fix no indexes, alter table drops and recreates the table newly, so indexes must be recreated as well
        // https://github.com/doctrine/dbal/pull/5486#issuecomment-1184957078
        $diff = clone $diff;
        $diff->addedIndexes = array_merge($diff->addedIndexes, $diff->fromTable->getIndexes());

        return parent::getAlterTableSQL($diff);
    }
}
