<?php

declare(strict_types=1);

namespace atk4\schema\Migration;

// NOT IMPLEMENTED !!!
use atk4\schema\Migration;

class Oracle extends Migration
{
    /** @var string Expression to create primary key */
    public $primary_key_expr = 'primary key';

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToPersistence = [
        ['varchar2', 255],
        'boolean' => ['number', 1],
        'integer' => ['number', 19],
        'money' => ['number', 12, 2],
        'float' => ['binary_double'],
        'date' => ['date'],
        'datetime' => ['date'], // in Oracle DATE data type is actually datetime
        'time' => ['varchar2', 8],
        'text' => ['varchar2', 1000],
        'array' => ['varchar2', 1000],
        'object' => ['varchar2', 1000],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [
        'date' => ['datetime'],
    ];

    public function __construct($source, $params = [])
    {
        // IDENTITY column or column with SEQUENCE as a default value is available only as of Oracle 12c
        // see https://stackoverflow.com/questions/10613846/create-table-with-sequence-nextval-in-oracle#10613875
        $this->templates['create'] = <<<'EOT'
            begin
                execute immediate 'create table {table} ([field])';
                execute immediate 'create or replace trigger {table_ai_trigger_before}
                        before insert on {table}
                        for each row
                        when (new."id" is null)
                    declare
                        last_id {table}."id"%type;
                    begin
                        select nvl(max("id"), 0) into last_id from {table};
                        :new."id" := last_id + 1;
                    end;';
            end;
            EOT;

        // DROP TABLE IF EXISTS is not directly supported
        // see https://stackoverflow.com/questions/1799128/oracle-if-table-exists
        $this->templates['drop'] = <<<'EOT'
            begin
                begin
                    execute immediate 'drop table {table}';
                exception
                    when others then
                        if sqlcode != -942 then
                            raise;
                        end if;
                end;
                begin
                    execute immediate 'drop trigger {table_ai_trigger_before}';
                exception
                    when others then
                        if sqlcode != -4080 then
                            raise;
                        end if;
                end;
            end;
            EOT;

        parent::__construct($source, $params);
    }

    public function table($table)
    {
        parent::table($table);

        $this['table_ai_trigger_before'] = $table . '_ai_trigger_before';

        return $this;
    }

    public function describeTable(string $table): array
    {
        return $this->connection->expr('pragma table_info({})', [$table])->get();
    }

    public function getSqlFieldType(?string $type, array $options = []): ?string
    {
        $res = parent::getSqlFieldType($type, $options);

        // remove unsupported "unsigned"
        $res = preg_replace('~ unsigned(?: |$)~', ' ', $res);

        return $res;
    }
}
