<?php

declare(strict_types=1);

namespace atk4\data\UserAction;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Class atk4\data\UserAction\Generic is deprecated. Use atk4\data\Model\UserAction instead', E_USER_DEPRECATED);
}

/**
 * @deprecated use \atk4\data\Model\UserAction instead - will be removed in dec-2020
 */
class Generic extends \atk4\data\Model\UserAction
{
}
