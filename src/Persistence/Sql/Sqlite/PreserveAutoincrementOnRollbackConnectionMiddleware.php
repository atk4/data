<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Sqlite;

use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;

class PreserveAutoincrementOnRollbackConnectionMiddleware extends AbstractConnectionMiddleware
{
    private static string $libraryVersion;

    /**
     * @return list<array{schema: string, sequence: string, value: string}>
     */
    protected function beforeRollback(): array
    {
        if ((self::$libraryVersion ?? null) === null) {
            $getLibraryVersionSql = (new Query())
                ->field('sqlite_version()')
                ->render()[0];
            self::$libraryVersion = $this->query($getLibraryVersionSql)->fetchOne();
        }

        if (version_compare(self::$libraryVersion, '3.37') < 0) {
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
                ->where('name', 'sqlite_sequence')
                ->render()[0];
            $schemas = $this->query($listSchemasSql)->fetchFirstColumn();
        }

        if ($schemas === []) {
            $res = [];
        } else {
            $listAutoincrementsSql = implode("\nUNION ALL\n", array_map(static function (string $schema) {
                return (new Query())
                    ->table($schema . '.sqlite_sequence')
                    ->field(new Expression('\'' . str_replace('\'', '\'\'', $schema) . '\''), 'schema')
                    ->field('name', 'sequence')
                    ->field('seq', 'value')
                    ->render()[0];
            }, $schemas));

            $res = $this->query($listAutoincrementsSql)->fetchAllAssociative();
        }

        return $res;
    }

    protected function afterRollback(array $beforeRollbackData): void
    {
        // TODO
    }

    #[\Override]
    public function exec(string $sql): int
    {
        $isRollback = str_starts_with(strtoupper(ltrim($sql)), 'ROLLBACK ');

        if ($isRollback) {
            $beforeRollbackData = $this->beforeRollback();
        }

        $res = parent::exec($sql);

        if ($isRollback) {
            $this->afterRollback($beforeRollbackData);
        }

        return $res;
    }

    #[\Override]
    public function rollBack()
    {
        $beforeRollbackData = $this->beforeRollback();

        $res = parent::rollBack();

        $this->afterRollback($beforeRollbackData);

        return $res;
    }
}
