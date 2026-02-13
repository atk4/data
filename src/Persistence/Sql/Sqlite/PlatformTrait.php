<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\PlatformFixColumnCommentTypeHintTrait;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\DBAL\Schema\TableDiff;

trait PlatformTrait
{
    // remove (and related property) once https://github.com/doctrine/dbal/pull/6411 is merged and released
    use PlatformFixColumnCommentTypeHintTrait;

    /** @var list<string> */
    private $requireCommentHintTypes = [
        'bigint',
    ];

    public function __construct()
    {
        if (Connection::isDbal3x()) {
            $this->disableSchemaEmulation(); // @phpstan-ignore method.notFound
        } else {
            parent::__construct();
        }
    }

    /**
     * @deprecated remove once DBAL 3.x support is dropped
     */
    public function getIdentifierQuoteCharacter(): string
    {
        return '`';
    }

    #[\Override]
    public function quoteSingleIdentifier($str): string
    {
        return '`' . str_replace('`', '``', $str) . '`';
    }

    #[\Override]
    public function getAlterTableSQL(TableDiff $diff): array
    {
        // fix https://github.com/doctrine/dbal/pull/5501
        if (Connection::isDbal3x()) { // needed probably for DBAL 4.x too, but there are no tests
            $diff = clone $diff;
            $diff->fromTable = clone $diff->getOldTable(); // @phpstan-ignore property.notFound
            foreach ($diff->getOldTable()->getForeignKeys() as $foreignKey) {
                \Closure::bind(static function () use ($foreignKey) {
                    $foreignKey->_localColumnNames = $foreignKey->createIdentifierMap($foreignKey->getUnquotedLocalColumns());
                }, null, ForeignKeyConstraint::class)();
            }
        }

        // fix no indexes, alter table drops and recreates the table newly, so indexes must be recreated as well
        // https://github.com/doctrine/dbal/pull/5486#issuecomment-1184957078
        if (Connection::isDbal3x()) { // needed probably for DBAL 4.x too, but there are no tests
            $diff = clone $diff;
            $diff->addedIndexes = array_merge($diff->addedIndexes, $diff->getOldTable()->getIndexes()); // @phpstan-ignore property.private, property.private
        }

        return parent::getAlterTableSQL($diff);
    }
}
