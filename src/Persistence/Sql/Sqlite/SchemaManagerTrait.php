<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Connection;
use Doctrine\DBAL\Driver\AbstractException as AbstractDriverException;
use Doctrine\DBAL\Exception as DbalException;
use Doctrine\DBAL\Schema\Identifier;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\TableDiff;

trait SchemaManagerTrait
{
    #[\Override]
    public function alterTable(TableDiff $tableDiff): void
    {
        $connection = Connection::isDbal3x()
            ? $this->_conn // @phpstan-ignore property.notFound
            : $this->connection;

        $hadForeignKeysEnabled = (bool) $connection->executeQuery('PRAGMA foreign_keys')->fetchOne();
        if ($hadForeignKeysEnabled) {
            $connection->executeStatement('PRAGMA foreign_keys = 0'); // @phpstan-ignore method.internal
        }

        parent::alterTable($tableDiff);

        if ($hadForeignKeysEnabled) {
            $connection->executeStatement('PRAGMA foreign_keys = 1'); // @phpstan-ignore method.internal

            $rows = $connection->executeQuery('PRAGMA foreign_key_check')->fetchAllAssociative();
            if (count($rows) > 0) {
                throw Connection::isDbal3x()
                    ? new DbalException('Foreign key constraints are violated') // @phpstan-ignore new.interface
                    : new class('Foreign key constraints are violated') extends AbstractDriverException {};
            }
        }
    }

    // fix collations unescape for SQLiteSchemaManager::parseColumnCollationFromSQL() method
    // https://github.com/doctrine/dbal/issues/6129

    /**
     * @param string $table
     * @param string $database
     */
    #[\Override]
    protected function _getPortableTableColumnList($table, $database, $tableColumns): array
    {
        $res = parent::_getPortableTableColumnList($table, $database, $tableColumns);
        foreach ($res as $column) {
            if ($column->hasPlatformOption('collation')) {
                $column->setPlatformOption('collation', $this->unquoteTableIdentifier($column->getPlatformOption('collation')));
            }
        }

        return $res;
    }

    // fix quoted table name support for private SQLiteSchemaManager::getCreateTableSQL() method
    // https://github.com/doctrine/dbal/blob/3.3.7/src/Schema/SqliteSchemaManager.php#L539
    // TODO submit a PR with fixed SQLiteSchemaManager to DBAL

    private function unquoteTableIdentifier(string $tableName): string
    {
        return (new Identifier($tableName))->getName();
    }

    /**
     * @param string $name
     *
     * @deprecated remove once DBAL 3.x support is dropped
     */
    public function listTableDetails($name): Table
    {
        return parent::listTableDetails( // @phpstan-ignore staticMethod.notFound
            Connection::isDbal3x()
                ? $this->unquoteTableIdentifier($name)
                : $name
        );
    }

    /**
     * @param string $table
     */
    #[\Override]
    public function listTableIndexes($table): array
    {
        return parent::listTableIndexes($this->unquoteTableIdentifier($table));
    }

    /**
     * @param string $table
     * @param string $database
     */
    #[\Override]
    public function listTableForeignKeys($table, $database = null): array
    {
        return parent::listTableForeignKeys($this->unquoteTableIdentifier($table), $database); // @phpstan-ignore arguments.count
    }

    /**
     * @param string $name
     */
    public function introspectTable($name): Table
    {
        return parent::introspectTable(
            Connection::isDbal3x()
                ? $name
                : $this->unquoteTableIdentifier($name)
        );
    }
}
