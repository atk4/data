<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

trait SchemaManagerTrait
{
    public function alterTable(TableDiff $tableDiff): void
    {
        $hadForeignKeysEnabled = (bool) $this->_conn->executeQuery('PRAGMA foreign_keys')->fetchOne();
        if ($hadForeignKeysEnabled) {
            $this->_execSql('PRAGMA foreign_keys = 0');
        }

        parent::alterTable($tableDiff);

        if ($hadForeignKeysEnabled) {
            $this->_execSql('PRAGMA foreign_keys = 1');

            $rows = $this->_conn->executeQuery('PRAGMA foreign_key_check')->fetchAllAssociative();
            if (count($rows) > 0) {
                throw new DbalException('Foreign key constraints are violated');
            }
        }
    }

    // fix collations unescape for SqliteSchemaManager::parseColumnCollationFromSQL() method
    // https://github.com/doctrine/dbal/issues/6129

    protected function _getPortableTableColumnList($table, $database, $tableColumns)
    {
        $res = parent::_getPortableTableColumnList($table, $database, $tableColumns);
        foreach ($res as $column) {
            if ($column->hasPlatformOption('collation')) {
                $column->setPlatformOption('collation', $this->unquoteTableIdentifier($column->getPlatformOption('collation')));
            }
        }

        return $res;
    }

    // fix quoted table name support for private SqliteSchemaManager::getCreateTableSQL() method
    // https://github.com/doctrine/dbal/blob/3.3.7/src/Schema/SqliteSchemaManager.php#L539
    // TODO submit a PR with fixed SqliteSchemaManager to DBAL

    private function unquoteTableIdentifier(string $tableName): string
    {
        return (new Identifier($tableName))->getName();
    }

    public function listTableDetails($name): Table
    {
        return parent::listTableDetails($this->unquoteTableIdentifier($name));
    }

    public function listTableIndexes($table): array
    {
        return parent::listTableIndexes($this->unquoteTableIdentifier($table));
    }

    public function listTableForeignKeys($table, $database = null): array
    {
        return parent::listTableForeignKeys($this->unquoteTableIdentifier($table), $database);
    }
}
