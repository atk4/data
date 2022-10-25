<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Oracle;

use Doctrine\DBAL\Result as DbalResult;

trait SchemaManagerTrait
{
    protected function selectTableNames(string $databaseName): DbalResult
    {
        // ignore Oracle maintained tables, improve tests performance
        // self::selectTableColumns() impl. once needed or wait for https://github.com/doctrine/dbal/issues/5764
        $sql = <<<'EOF'
            SELECT all_tables.table_name
            FROM sys.all_tables
            INNER JOIN sys.user_objects ON user_objects.object_type = 'TABLE'
                AND user_objects.object_name = all_tables.table_name
            WHERE owner = :OWNER AND oracle_maintained = 'N'
            ORDER BY all_tables.table_name
            EOF;

        return $this->_conn->executeQuery($sql, ['OWNER' => $databaseName]);
    }
}
