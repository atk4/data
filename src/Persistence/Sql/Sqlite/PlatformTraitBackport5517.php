<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\Identifier;

/**
 * Drop once https://github.com/doctrine/dbal/pull/5517 is merged and DBAL 3.3.x support is removed.
 */
trait PlatformTraitBackport5517
{
    private function emulateSchemaNamespacing(string $tableName): string
    {
        return $tableName;
    }

    public function getForeignKeyDeclarationSQL(ForeignKeyConstraint $foreignKey)
    {
        return parent::getForeignKeyDeclarationSQL(new ForeignKeyConstraint(
            $foreignKey->getQuotedLocalColumns($this),
            $this->emulateSchemaNamespacing($foreignKey->getQuotedForeignTableName($this)),
            $foreignKey->getQuotedForeignColumns($this),
            $foreignKey->getName(),
            $foreignKey->getOptions()
        ));
    }

    protected function _getCreateTableSQL($name, array $columns, array $options = [])
    {
        $name = $this->emulateSchemaNamespacing($name);
        $queryFields = $this->getColumnDeclarationListSQL($columns);

        if (isset($options['uniqueConstraints']) && !empty($options['uniqueConstraints'])) {
            foreach ($options['uniqueConstraints'] as $constraintName => $definition) {
                $queryFields .= ', ' . $this->getUniqueConstraintDeclarationSQL($constraintName, $definition);
            }
        }

        $queryFields .= \Closure::bind(fn () => $this->getNonAutoincrementPrimaryKeyDefinition($columns, $options), $this, parent::class)();

        if (isset($options['foreignKeys'])) {
            foreach ($options['foreignKeys'] as $foreignKey) {
                $queryFields .= ', ' . $this->getForeignKeyDeclarationSQL($foreignKey);
            }
        }

        $tableComment = '';
        if (isset($options['comment'])) {
            $comment = trim($options['comment'], " '");

            $tableComment = \Closure::bind(fn () => $this->getInlineTableCommentSQL($comment), $this, parent::class)();
        }

        $query = ['CREATE TABLE ' . $name . ' ' . $tableComment . '(' . $queryFields . ')'];

        if (isset($options['alter']) && $options['alter'] === true) {
            return $query;
        }

        if (isset($options['indexes']) && !empty($options['indexes'])) {
            foreach ($options['indexes'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $name);
            }
        }

        if (isset($options['unique']) && !empty($options['unique'])) {
            foreach ($options['unique'] as $indexDef) {
                $query[] = $this->getCreateIndexSQL($indexDef, $name);
            }
        }

        return $query;
    }

    public function getListTableConstraintsSQL($table)
    {
        $table = $this->emulateSchemaNamespacing($table);

        return sprintf(
            "SELECT sql FROM sqlite_master WHERE type='index' AND tbl_name = %s AND sql NOT NULL ORDER BY name",
            $this->quoteStringLiteral($table)
        );
    }

    public function getListTableColumnsSQL($table, $database = null)
    {
        $table = $this->emulateSchemaNamespacing($table);

        return sprintf('PRAGMA table_info(%s)', $this->quoteStringLiteral($table));
    }

    public function getListTableIndexesSQL($table, $database = null)
    {
        $table = $this->emulateSchemaNamespacing($table);

        return sprintf('PRAGMA index_list(%s)', $this->quoteStringLiteral($table));
    }

    public function getTruncateTableSQL($tableName, $cascade = false)
    {
        $tableIdentifier = new Identifier($tableName);
        $tableName = $this->emulateSchemaNamespacing($tableIdentifier->getQuotedName($this));

        return 'DELETE FROM ' . $tableName;
    }

    public function getTemporaryTableName($tableName)
    {
        $tableName = $this->emulateSchemaNamespacing($tableName);

        return $tableName;
    }

    public function getListTableForeignKeysSQL($table, $database = null)
    {
        $table = $this->emulateSchemaNamespacing($table);

        return sprintf('PRAGMA foreign_key_list(%s)', $this->quoteStringLiteral($table));
    }
}
