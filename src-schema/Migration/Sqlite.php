<?php

declare(strict_types=1);

namespace atk4\schema\Migration;

class Sqlite extends \atk4\schema\Migration
{
    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [
        0 => ['string'],
    ];

    public function describeTable(string $table): array
    {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
    }

    public function getSqlFieldType(?string $type, array $options = []): ?string
    {
        $res = parent::getSqlFieldType($type, $options);

        // fix PK datatype to "integer primary key"
        // see https://www.sqlite.org/lang_createtable.html#rowid
        // all other datatypes (like "bigint", "integer unsinged", "integer not null") are not supported
        if (!empty($options['ref_type']) && $options['ref_type'] === self::REF_TYPE_PRIMARY) {
            $res = preg_replace('~(?:big)?int(?:eger)?\s+(unsigned\s+)?(not null\s+)?~', 'integer ', $res);
        }

        return $res;
    }
}
