<?php

declare(strict_types=1);

namespace atk4\data;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Class atk4\data\Join is deprecated. Use atk4\data\Model\Join instead', E_USER_DEPRECATED);
}

/**
 * @deprecated use \atk4\data\Model\Join instead - will be removed in dec-2020
 */
class Join extends Model\Join
{
}
