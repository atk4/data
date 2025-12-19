<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Atk4\Data\Persistence\Sql\Connection;
use Atk4\Data\Persistence\Sql\PlatformFixColumnCommentTypeHintTrait;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\AbstractAsset;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Sequence;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Schema\UniqueConstraint;

trait PlatformTrait
{
    use PlatformFixColumnCommentTypeHintTrait;

    /** @var list<string> */
    private $requireCommentHintTypes = [
        'binary',
        'blob',
        'time',
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

    // Oracle database requires explicit conversion when using binary column,
    // workaround by using a standard non-binary column with custom encoding/typecast

    #[\Override]
    public function getBinaryTypeDeclarationSQL(array $column): string
    {
        $lengthEncodedAscii = ($column['length'] ?? 255) * 2 + strlen("atk4_binary\ru5f8mzx4vsm8g2c9\r" . hash('crc32b', ''));
        $column['length'] = intdiv($lengthEncodedAscii + 3, 4);

        return $this->getStringTypeDeclarationSQL($column);
    }

    #[\Override]
    public function getBlobTypeDeclarationSQL(array $column): string
    {
        return $this->getClobTypeDeclarationSQL($column);
    }

    // TODO create DBAL PR
    #[\Override]
    public function getFloatDeclarationSQL(array $column): string
    {
        return 'BINARY_DOUBLE';
    }

    // TODO create DBAL PR
    #[\Override]
    public function getTimeTypeDeclarationSQL(array $column): string
    {
        $column['length'] = 26;

        return parent::getStringTypeDeclarationSQL($column);
    }

    // Oracle DBAL platform autoincrement implementation does not increment like
    // Sqlite or MySQL does, unify the behavior

    #[\Override]
    public function getCreateSequenceSQL(Sequence $sequence): string
    {
        $sequence->setCache(1);

        return parent::getCreateSequenceSQL($sequence);
    }

    /**
     * @param string $name
     * @param string $table
     * @param int    $start
     */
    #[\Override]
    public function getCreateAutoincrementSql($name, $table, $start = 1): array
    {
        $sqls = parent::getCreateAutoincrementSql($name, $table, $start); // @phpstan-ignore method.internal

        // fix table name when name /w schema is used
        // TODO submit a PR with fixed OraclePlatform to DBAL
        $sqls[0] = preg_replace('~(?<=WHERE TABLE_NAME = \').+\.(?=.+?\')~', '', $sqls[0]);

        // replace trigger from https://github.com/doctrine/dbal/blob/3.1.3/src/Platforms/OraclePlatform.php#L526-L546
        $tableIdentifier = \Closure::bind(fn () => $this->normalizeIdentifier($table), $this, OraclePlatform::class)();
        $nameIdentifier = \Closure::bind(fn () => $this->normalizeIdentifier($name), $this, OraclePlatform::class)();
        $aiTriggerName = \Closure::bind(fn () => $this->getAutoincrementIdentifierName($tableIdentifier), $this, OraclePlatform::class)();
        $aiSequenceName = $this->getIdentitySequenceName($tableIdentifier->getQuotedName($this), $nameIdentifier->getQuotedName($this)); // @phpstan-ignore method.internal, arguments.count
        assert(str_starts_with($sqls[array_key_last($sqls)], 'CREATE TRIGGER ' . $aiTriggerName . "\n"));

        $pkSeq = \Closure::bind(fn () => $this->normalizeIdentifier($aiSequenceName), $this, OraclePlatform::class)()->getName();
        $sqls[array_key_last($sqls)] = (new Expression(
            // else branch should be maybe (because of concurrency) put into after update trigger
            str_replace('[pk_seq]', '\'' . str_replace('\'', '\'\'', $pkSeq) . '\'', <<<'EOF'
                CREATE TRIGGER {{trigger}}
                    BEFORE INSERT OR UPDATE
                    ON {{table}}
                    FOR EACH ROW
                DECLARE
                    atk4__pk_seq_last__ {{table}}.{pk}%TYPE;
                BEGIN
                    IF (:NEW.{pk} IS NULL) THEN
                        SELECT {{pk_seq}}.NEXTVAL INTO :NEW.{pk} FROM DUAL;
                    ELSE
                        SELECT LAST_NUMBER INTO atk4__pk_seq_last__ FROM USER_SEQUENCES WHERE SEQUENCE_NAME = [pk_seq];
                        WHILE atk4__pk_seq_last__ <= :NEW.{pk}
                        LOOP
                            SELECT {{pk_seq}}.NEXTVAL + 1 INTO atk4__pk_seq_last__ FROM DUAL;
                        END LOOP;
                    END IF;
                END;
                EOF),
            [
                'trigger' => \Closure::bind(fn () => $this->normalizeIdentifier($aiTriggerName), $this, OraclePlatform::class)()->getName(),
                'table' => $tableIdentifier->getName(),
                'pk' => $nameIdentifier->getName(),
                'pk_seq' => $pkSeq,
            ]
        ))->render()[0];

        return $sqls;
    }

    /**
     * @param string|Table $table
     */
    #[\Override]
    public function getCreateIndexSQL(Index $index, $table): string
    {
        // workaround https://github.com/doctrine/dbal/issues/5508
        // no side effect on multiple null values or DBAL index list observed
        if ($index->isUnique()) {
            $uniqueConstraint = new UniqueConstraint(
                '0.0',
                ['0.0'],
                $index->getFlags(),
                $index->getOptions()
            );
            \Closure::bind(static function () use ($index, $uniqueConstraint) {
                $uniqueConstraint->_name = $index->_name;
                $uniqueConstraint->_namespace = $index->_namespace;
                $uniqueConstraint->_quoted = $index->_quoted;
                $uniqueConstraint->columns = $index->_columns;
            }, null, AbstractAsset::class)();

            $tableName = $table instanceof Table ? $table->getQuotedName($this) : $table;

            return $this->getCreateUniqueConstraintSQL($uniqueConstraint, $tableName);
        }

        return parent::getCreateIndexSQL($index, $table);
    }

    #[\Override]
    public function getListDatabasesSQL(): string
    {
        // ignore Oracle maintained schemas, improve tests performance
        // self::getListTablesSQL() is never used, thanks to https://github.com/doctrine/dbal/pull/5268 replaced by OracleSchemaManager::selectTableNames()
        // self::getListViewsSQL() does not need filtering, as there is no Oracle VIEW by default
        return 'SELECT username FROM sys.all_users'
            . ' WHERE oracle_maintained = \'N\'';
    }
}
