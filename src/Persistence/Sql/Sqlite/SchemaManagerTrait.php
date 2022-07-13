<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Exception;
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
                throw (new Exception('Foreign key constraints are violated'))
                    ->addMoreInfo('data', $rows);
            }
        }
    }

    protected function _getPortableTableForeignKeysList($tableForeignKeys): array
    {
        $foreignKeys = parent::_getPortableTableForeignKeysList($tableForeignKeys);

        // fix https://github.com/doctrine/dbal/pull/5486/files#r920239919
        foreach ($foreignKeys as $foreignKey) {
            if (ctype_digit($foreignKey->getName())) {
                \Closure::bind(function () use ($foreignKey) {
                    $foreignKey->_name = null;
                }, null, Identifier::class)();
            }
        }

        return $foreignKeys;
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
