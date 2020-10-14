<?php

declare(strict_types=1);

namespace atk4\schema\Migration;

class Mssql extends \atk4\schema\Migration
{
    use \atk4\dsql\Mssql\ExpressionTrait;

    protected $escape_char = ']';

    /** @var string Expression to create primary key */
    public $primary_key_expr = 'primary key identity';

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToPersistence = [
        'boolean' => ['bit'],
    ];

    /** @var array use this array in extended classes to overwrite or extend values of default mapping */
    public $mapToAgile = [
        'bit' => ['boolean'],
    ];

    public function describeTable(string $table): array
    {
        throw new \atk4\data\Exception('not implemented');
    }

    public function getSqlFieldType(?string $type, array $options = []): ?string
    {
        $res = parent::getSqlFieldType($type, $options);

        // remove unsupported "unsigned"
        $res = preg_replace('~ unsigned(?: |$)~', ' ', $res);

        return $res;
    }
}
