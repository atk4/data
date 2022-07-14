<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\TableDiff;

trait PlatformTrait
{
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
    // TODO submit a PR with fixed SqlitePlatform to DBAL

    private function unquoteTableIdentifier(string $tableName): string
    {
        return (new Identifier($tableName))->getName();
    }

    public function getListTableConstraintsSQL($table)
    {
        return parent::getListTableConstraintsSQL($this->unquoteTableIdentifier($table));
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        return parent::getListTableColumnsSQL($this->unquoteTableIdentifier($table), $database);
    }

    public function getListTableIndexesSQL($table, $database = null)
    {
        return parent::getListTableIndexesSQL($this->unquoteTableIdentifier($table), $database);
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        return parent::getListTableForeignKeysSQL($this->unquoteTableIdentifier($table), $database);
    }

    public function getAlterTableSQL(TableDiff $diff): array
    {
        // fix https://github.com/doctrine/dbal/pull/5501
        $diff = clone $diff;
        $diff->fromTable = clone $diff->fromTable;
        foreach ($diff->fromTable->getForeignKeys() as $foreignKey) {
            \Closure::bind(function () use ($foreignKey) {
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
