<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence\Sql\Mysql;

use Atk4\Data\Persistence\Sql\Connection as BaseConnection;

class Connection extends BaseConnection
{
    protected $queryClass = Query::class;
    protected $expressionClass = Expression::class;
}
