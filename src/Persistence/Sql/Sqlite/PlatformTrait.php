<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\TableDiff;

trait PlatformTrait
{
    public function getIdentifierQuoteCharacter(): string
    {
        return '`';
    }

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
