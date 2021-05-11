<?php

declare(strict_types=1);

namespace Atk4\Dsql\Postgresql;

use Atk4\Dsql\Connection as BaseConnection;

class Connection extends BaseConnection
{
    protected $query_class = Query::class;
}
