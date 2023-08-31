<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;

trait PlatformTrait
{
    // standard PostgreSQL character types are case sensitive, unify the behaviour with other databases
    // with custom case insensitive types

    /**
     * @return array<int, string>
     */
    private function getCreateCaseInsensitiveDomainsSql(): array
    {
        $sqls = [];

        $sqls[] = 'DO' . "\n"
            . '$$' . "\n"
            . 'BEGIN' . "\n"
            . '    CREATE EXTENSION IF NOT EXISTS citext;' . "\n"
            . implode("\n", array_map(static function (string $domain): string {
                return '    IF to_regtype(\'' . $domain . '\') IS NULL THEN' . "\n"
                    . '        CREATE DOMAIN ' . $domain . ' AS citext;' . "\n"
                    . '    END IF;';
            }, ['atk4__cichar', 'atk4__civarchar'])) . "\n"
            . 'END' . "\n"
            . '$$';

        return $sqls;
    }

    protected function getVarcharTypeDeclarationSQLSnippet($length, $fixed)
    {
        return $fixed ? 'ATK4__CICHAR' : 'ATK4__CIVARCHAR';
    }

    public function getClobTypeDeclarationSQL(array $column)
    {
        return 'CITEXT';
    }

    protected function initializeDoctrineTypeMappings(): void
    {
        parent::initializeDoctrineTypeMappings();

        // https://github.com/doctrine/dbal/pull/5495
        $this->doctrineTypeMapping['citext'] = 'text';
    }

    public function getCurrentDatabaseExpression(bool $includeSchema = false): string
    {
        if ($includeSchema) {
            return 'CONCAT(CURRENT_DATABASE(), \'.\', CURRENT_SCHEMA())';
        }

        return parent::getCurrentDatabaseExpression();
    }

    // PostgreSQL DBAL platform uses SERIAL column type for autoincrement which does not increment
    // when a row with a not-null PK is inserted like Sqlite or MySQL does, unify the behaviour

    private function getPrimaryKeyColumn(Table $table): ?Column
    {
        if ($table->getPrimaryKey() === null) {
            return null;
        }

        return $table->getColumn($table->getPrimaryKey()->getColumns()[0]);
    }

    /**
     * @return array<int, string>
     */
    protected function getCreateAutoincrementSql(Table $table, Column $pkColumn): array
    {
        $sqls = [];

        $pkSeqName = $this->getIdentitySequenceName($table->getName(), $pkColumn->getName());

        $conn = new Connection();

        $sqls[] = $conn->expr(
            // else branch should be maybe (because of concurrency) put into after update trigger
            // with pure nextval instead of setval with a loop like in Oracle trigger
            str_replace('[pk_seq]', '\'' . $pkSeqName . '\'', <<<'EOF'
                CREATE OR REPLACE FUNCTION {{trigger_func}}()
                RETURNS trigger AS $$
                DECLARE
                    atk4__pk_seq_last__ {table}.{pk}%TYPE;
                BEGIN
                    IF (NEW.{pk} IS NULL) THEN
                        NEW.{pk} := nextval([pk_seq]);
                    ELSE
                        SELECT COALESCE(last_value, 0) INTO atk4__pk_seq_last__ FROM {{pk_seq}};
                        IF (atk4__pk_seq_last__ <= NEW.{pk}) THEN
                            atk4__pk_seq_last__  := setval([pk_seq], NEW.{pk}, true);
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql
                EOF),
            [
                'table' => $table->getShortestName($table->getNamespaceName()), // TODO should be probably name /w schema, but it is not supported, get variable type differently
                'pk' => $pkColumn->getName(),
                'pk_seq' => $pkSeqName,
                'trigger_func' => $table->getName() . '_AI_FUNC',
            ]
        )->render()[0];

        $sqls[] = $conn->expr(
            <<<'EOF'
                CREATE TRIGGER {trigger}
                    BEFORE INSERT OR UPDATE
                    ON {{table}}
                    FOR EACH ROW
                EXECUTE PROCEDURE {{trigger_func}}()
                EOF,
            [
                'table' => $table->getName(),
                'trigger' => $table->getShortestName($table->getNamespaceName()) . '_AI_PK',
                'trigger_func' => $table->getName() . '_AI_FUNC',
            ]
        )->render()[0];

        return $sqls;
    }

    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES)
    {
        $sqls = array_merge(
            $this->getCreateCaseInsensitiveDomainsSql(),
            parent::getCreateTableSQL($table, $createFlags)
        );

        $pkColumn = $this->getPrimaryKeyColumn($table);
        if ($pkColumn !== null) {
            $sqls = array_merge($sqls, $this->getCreateAutoincrementSql($table, $pkColumn));
        }

        return $sqls;
    }
}
