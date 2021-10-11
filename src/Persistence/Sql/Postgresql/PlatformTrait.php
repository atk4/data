<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Postgresql;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Table;

trait PlatformTrait
{
    // PostgreSQL DBAL platform uses SERIAL column type for autoincrement which does not increment
    // when a row with a not-null PK is inserted like Sqlite or MySQL does, unify the behaviour

    private function getPrimaryKeyColumn(Table $table): ?Column
    {
        if ($table->getPrimaryKey() === null) {
            return null;
        }

        return $table->getColumn($table->getPrimaryKey()->getColumns()[0]);
    }

    protected function getCreateAutoincrementSql(Table $table, Column $pkColumn): array
    {
        $sqls = [];


        $conn = new Connection();


        $pkColName = $pkColumn->getName();


        $t = $table->getName();
        $pkseq = $t . '_' . $pkColName . '_seq';

        $sqls[] = $conn->expr(
            // else branch should be maybe (because of concurrency) put into after update trigger
            // with pure nextval instead of setval with a loop like in Oracle trigger
            str_replace('[pk_seq]', '\'' . $pkseq . '\'', <<<'EOF'
                CREATE OR REPLACE FUNCTION {trigger_func}()
                RETURNS trigger AS $$
                DECLARE
                    atk4__pk_seq_last__ {table}.{pk}%TYPE;
                BEGIN
                    IF (NEW.{pk} IS NULL) THEN
                        NEW.{pk} := nextval([pk_seq]);
                    ELSE
                        SELECT COALESCE(last_value, 0) INTO atk4__pk_seq_last__ FROM {pk_seq};
                        IF (atk4__pk_seq_last__ <= NEW.{pk}) THEN
                            atk4__pk_seq_last__  := setval([pk_seq], NEW.{pk}, true);
                        END IF;
                    END IF;
                    RETURN NEW;
                END;
                $$ LANGUAGE plpgsql;
                EOF),
            [
                'table' => $t,
                'pk' => $pkColName,
                'pk_seq' => $pkseq,
                'trigger_func' => $t . '_func',
            ]
            // TODO should not exist... (no OR REPLACE) also for Oracle
        )->render();
        // function is not dropped when table is, we should use only one


        $sqls[] = $conn->expr(
            str_replace('[pk_seq]', '\'' . $pkseq . '\'', <<<'EOF'
                CREATE TRIGGER {trigger}
                BEFORE INSERT OR UPDATE
                ON {table}
                FOR EACH ROW
                EXECUTE PROCEDURE {trigger_func}();
                EOF),
            [
                'table' => $t,
                'trigger' => $t . '_tri',
                'trigger_func' => $t . '_func',
            ]
        )->render();
        // TODO procedure name/fucntion
        // TODO test if it is deleteted when the table it


        return $sqls;
    }

    public function getCreateTableSQL(Table $table, $createFlags = self::CREATE_INDEXES)
    {
        $sqls = parent::getCreateTableSQL($table, $createFlags);

        $pkColumn = $this->getPrimaryKeyColumn($table);
        if ($pkColumn !== null) {
            $sqls = array_merge($sqls, $this->getCreateAutoincrementSql($table, $pkColumn));
        }

        return $sqls;
    }

    /*
    public function getCreateAutoincrementSql($name, $table, $start = 1)
    {
        $sqls = parent::getCreateAutoincrementSql($name, $table, $start);

        // replace https://github.com/doctrine/dbal/blob/3.1.3/src/Platforms/OraclePlatform.php#L526-L546
        $tableIdentifier = \Closure::bind(fn () => $this->normalizeIdentifier($table), $this, OraclePlatform::class)();
        $nameIdentifier = \Closure::bind(fn () => $this->normalizeIdentifier($name), $this, OraclePlatform::class)();
        $aiTriggerName = \Closure::bind(fn () => $this->getAutoincrementIdentifierName($tableIdentifier), $this, OraclePlatform::class)();
        $aiSequenceName = $this->getIdentitySequenceName($tableIdentifier->getQuotedName($this), $nameIdentifier->getQuotedName($this));
        assert(str_starts_with($sqls[count($sqls) - 1], 'CREATE TRIGGER ' . $aiTriggerName . "\n"));

        $conn = new Connection();
        $pkSeq = \Closure::bind(fn () => $this->normalizeIdentifier($aiSequenceName), $this, OraclePlatform::class)()->getName();
        $sqls[count($sqls) - 1] = $conn->expr(
            str_replace('[pk_seq]', '\'' . $pkSeq . '\'', <<<'EOT'
                CREATE OR REPLACE TRIGGER {trigger}
                    BEFORE INSERT OR UPDATE
                    ON {table}
                    FOR EACH ROW
                DECLARE
                    pk_seq_last {table}.{pk}%TYPE;
                BEGIN
                    IF (NVL(:NEW.{pk}, 0) = 0) THEN
                        SELECT {pk_seq}.NEXTVAL INTO :NEW.{pk} FROM DUAL;
                    ELSE
                        SELECT NVL(LAST_NUMBER, 0) INTO pk_seq_last FROM USER_SEQUENCES WHERE SEQUENCE_NAME = [pk_seq];
                        WHILE pk_seq_last <= :NEW.{pk}
                        LOOP
                            SELECT {pk_seq}.NEXTVAL + 1 INTO pk_seq_last FROM DUAL;
                        END LOOP;
                    END IF;
                END;
                EOT),
            [
                'trigger' => \Closure::bind(fn () => $this->normalizeIdentifier($aiTriggerName), $this, OraclePlatform::class)()->getName(),
                'table' => $tableIdentifier->getName(),
                'pk' => $nameIdentifier->getName(),
                'pk_seq' => $pkSeq,
            ]
        )->render();

        return $sqls;
    }*/
}
