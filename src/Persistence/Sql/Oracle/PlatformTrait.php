<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Schema\Sequence;

trait PlatformTrait
{
    public function getVarcharTypeDeclarationSQL(array $column)
    {
        $column['length'] = ($column['length'] ?? 255) * 4;

        return parent::getVarcharTypeDeclarationSQL($column);
    }

    // Oracle database requires explicit conversion when using binary column,
    // workaround by using a standard non-binary column with custom encoding/typecast

    public function getBinaryTypeDeclarationSQL(array $column)
    {
        $lengthEncodedAscii = ($column['length'] ?? 255) * 2 + strlen('atk__binary__u5f8mzx4vsm8g2c9__' . hash('crc32b', ''));
        $column['length'] = intdiv($lengthEncodedAscii + 3, 4);

        return $this->getVarcharTypeDeclarationSQL($column);
    }

    public function getBlobTypeDeclarationSQL(array $column)
    {
        return $this->getClobTypeDeclarationSQL($column);
    }

    // TODO test DBAL DB diff for each supported Field type
    // then fix using https://github.com/doctrine/dbal/issues/5194#issuecomment-1018790220
//    protected function initializeCommentedDoctrineTypes()
//    {
//        parent::initializeCommentedDoctrineTypes();
//
//        $this->markDoctrineTypeCommented('binary');
//        $this->markDoctrineTypeCommented('blob');
//    }

    // Oracle DBAL platform autoincrement implementation does not increment like
    // Sqlite or MySQL does, unify the behaviour

    public function getCreateSequenceSQL(Sequence $sequence)
    {
        $sequence->setCache(1);

        return parent::getCreateSequenceSQL($sequence);
    }

    public function getCreateAutoincrementSql($name, $table, $start = 1)
    {
        $sqls = parent::getCreateAutoincrementSql($name, $table, $start);

        // replace trigger from https://github.com/doctrine/dbal/blob/3.1.3/src/Platforms/OraclePlatform.php#L526-L546
        $tableIdentifier = \Closure::bind(fn () => $this->normalizeIdentifier($table), $this, OraclePlatform::class)();
        $nameIdentifier = \Closure::bind(fn () => $this->normalizeIdentifier($name), $this, OraclePlatform::class)();
        $aiTriggerName = \Closure::bind(fn () => $this->getAutoincrementIdentifierName($tableIdentifier), $this, OraclePlatform::class)();
        $aiSequenceName = $this->getIdentitySequenceName($tableIdentifier->getQuotedName($this), $nameIdentifier->getQuotedName($this));
        assert(str_starts_with($sqls[count($sqls) - 1], 'CREATE TRIGGER ' . $aiTriggerName . "\n"));

        $conn = new Connection();
        $pkSeq = \Closure::bind(fn () => $this->normalizeIdentifier($aiSequenceName), $this, OraclePlatform::class)()->getName();
        $sqls[count($sqls) - 1] = $conn->expr(
            // else branch should be maybe (because of concurrency) put into after update trigger
            str_replace('[pk_seq]', '\'' . $pkSeq . '\'', <<<'EOT'
                CREATE TRIGGER {trigger}
                    BEFORE INSERT OR UPDATE
                    ON {table}
                    FOR EACH ROW
                DECLARE
                    atk4__pk_seq_last__ {table}.{pk}%TYPE;
                BEGIN
                    IF (:NEW.{pk} IS NULL) THEN
                        SELECT {pk_seq}.NEXTVAL INTO :NEW.{pk} FROM DUAL;
                    ELSE
                        SELECT LAST_NUMBER INTO atk4__pk_seq_last__ FROM USER_SEQUENCES WHERE SEQUENCE_NAME = [pk_seq];
                        WHILE atk4__pk_seq_last__ <= :NEW.{pk}
                        LOOP
                            SELECT {pk_seq}.NEXTVAL + 1 INTO atk4__pk_seq_last__ FROM DUAL;
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
        )->render()[0];

        return $sqls;
    }
}
