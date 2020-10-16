<?php

declare(strict_types=1);

namespace atk4\schema\Migration;

class Mysql extends \atk4\schema\Migration
{
    protected $escape_char = '`';

    /** @var string Expression to create primary key */
    public $primary_key_expr = 'primary key auto_increment';

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToPersistence = [
        'text' => ['longtext'],
        'array' => ['longtext'],
        'object' => ['longtext'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [
        0 => ['string'],
        'longtext' => ['text'],
        'longblob' => ['text'],
    ];

    public function describeTable(string $table): array
    {
        if (!$this->connection->expr('show tables like []', [$table])->get()) {
            return []; // no such table
        }

        $result = [];

        foreach ($this->connection->expr('describe {}', [$table]) as $row) {
            $row2 = [];
            $row2['name'] = $row['Field'];
            $row2['pk'] = $row['Key'] === 'PRI';
            $row2['type'] = preg_replace('/\(.*/', '', $row['Type']);

            $result[] = $row2;
        }

        return $result;
    }
}
