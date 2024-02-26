<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Atk4\Data\Persistence\Sql\Exception;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

class PreserveAutoincrementOnRollbackConnectionMiddleware extends AbstractConnectionMiddleware
{
    private function createExpressionFromStringLiteral(string $value): Expression
    {
        return new Expression('\'' . str_replace('\'', '\'\'', $value) . '\'');
    }

    /**
     * @return array<string, array<string, int>>
     */
    protected function listSequences(): array
    {
        if (version_compare(Connection::getDriverVersion(), '3.37') < 0) {
            $listAllSchemasSql = (new Query())
                ->table('pragma_database_list')
                ->field('name')
                ->render()[0];
            $allSchemas = $this->query($listAllSchemasSql)->fetchFirstColumn();

            $schemas = [];
            foreach ($allSchemas as $schema) {
                $dummySelectFromSqliteSequenceTableSql = (new Query())
                    ->table($schema . '.sqlite_sequence')
                    ->field('name')
                    ->render()[0];
                try {
                    $this->query($dummySelectFromSqliteSequenceTableSql)->fetchFirstColumn();
                    $schemas[] = $schema;
                } catch (\Exception $e) {
                    while ($e->getPrevious() !== null) {
                        $e = $e->getPrevious();
                    }

                    if (!str_contains($e->getMessage(), 'HY000')
                        || !str_contains($e->getMessage(), 'no such table: ' . $schema . '.sqlite_sequence')
                    ) {
                        throw $e;
                    }
                }
            }
        } else {
            $listSchemasSql = (new Query())
                ->table('pragma_table_list')
                ->field('schema')
                ->where('name', $this->createExpressionFromStringLiteral('sqlite_sequence'))
                ->render()[0];
            $schemas = $this->query($listSchemasSql)->fetchFirstColumn();
        }

        $res = [];
        if ($schemas !== []) {
            $listSequencesSql = implode("\nUNION ALL\n", array_map(function (string $schema) {
                return (new Query())
                    ->table($schema . '.sqlite_sequence')
                    ->field($this->createExpressionFromStringLiteral($schema), 'schema')
                    ->field('name')
                    ->field('seq', 'value')
                    ->render()[0];
            }, $schemas));

            $res = [];
            foreach ($this->query($listSequencesSql)->fetchAllAssociative() as $row) {
                $value = (int) $row['value'];
                if (!is_int($row['value']) && (string) $value !== $row['value']) {
                    throw (new Exception('Unexpected SQLite sequence value'))
                        ->addMoreInfo('value', $row['value']);
                }

                $res[$row['schema']][$row['name']] = $value;
            }
        }

        return $res;
    }

    /**
     * @param array<string, array<string, int>> $beforeRollbackSequences
     */
    protected function restoreSequencesIfDecremented(array $beforeRollbackSequences): void
    {
        $afterRollbackSequences = $this->listSequences();

        foreach ($beforeRollbackSequences as $schema => $beforeRollbackSequences2) {
            foreach ($beforeRollbackSequences2 as $table => $beforeRollbackValue) {
                $afterRollbackValue = $afterRollbackSequences[$schema][$table] ?? null;
                if ($afterRollbackValue >= $beforeRollbackValue) {
                    continue;
                }

                if ($afterRollbackValue === null) { // https://sqlite.org/forum/info/3e7cc380f0a159c6
                    $query = (new Query())
                        ->mode('insert')
                        ->set('name', $this->createExpressionFromStringLiteral($table));
                } else {
                    $query = (new Query())
                        ->mode('update')
                        ->where('name', $this->createExpressionFromStringLiteral($table));
                }
                $query->table($schema . '.sqlite_sequence');
                $query->set('seq', $this->createExpressionFromStringLiteral((string) $beforeRollbackValue));

                $this->exec($query->render()[0]);
            }
        }
    }

    #[\Override]
    public function exec(string $sql): int
    {
        $isRollback = str_starts_with(strtoupper(ltrim($sql)), 'ROLLBACK ');

        if ($isRollback) {
            $beforeRollbackSequences = $this->listSequences();
        }

        $res = parent::exec($sql);

        if ($isRollback) {
            $this->restoreSequencesIfDecremented($beforeRollbackSequences);
        }

        return $res;
    }

    #[\Override]
    public function rollBack()
    {
        $beforeRollbackSequences = $this->listSequences();

        $res = parent::rollBack();

        $this->restoreSequencesIfDecremented($beforeRollbackSequences);

        return $res;
    }
}
