<?php

declare(strict_types=1);

namespace Atk4\Data\Model\Phpstan;

interface PhpdocTypeInterface
{
    /**
     * @deprecated Interface for phpdoc type for Phpstan only
     *
     * @return never
     */
    public function neverImplement(): void;
}
