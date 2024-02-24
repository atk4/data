<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Array_\Db;

/**
 * @method TableArray getOwner()
 */
class ColumnArray extends ColumnAbstract
{
    public function __construct(TableArray $owner, string $columnName)
    {
        parent::__construct($owner, $columnName);
    }
}
