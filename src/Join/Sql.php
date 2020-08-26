<?php

declare(strict_types=1);

namespace atk4\data\Join;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Class atk4\data\Join\Sql is deprecated. Use atk4\data\Persistence\Sql\Join instead', E_USER_DEPRECATED);
}

/**
 * @deprecated use \atk4\data\Persistence\Sql\Join instead - will be removed in dec-2020
 */
class Sql extends \atk4\data\Persistence\Sql\Join
{
}
