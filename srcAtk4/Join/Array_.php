<?php

declare(strict_types=1);

namespace Atk4\Data\Join;

if (!class_exists(\SebastianBergmann\CodeCoverage\CodeCoverage::class, false)) {
    'trigger_error'('Class atk4\data\Join\Array_ is deprecated. Use atk4\data\Persistence\Array_\Join instead', E_USER_DEPRECATED);
}

/**
 * @deprecated use \atk4\data\Persistence\Array_\Join instead - will be removed in dec-2020
 */
class Array_ extends \atk4\data\Persistence\Array_\Join
{
}
