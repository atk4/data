<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Schema\Identifier;

trait PlatformTrait
{
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
}
