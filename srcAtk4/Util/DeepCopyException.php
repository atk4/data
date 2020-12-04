<?php

declare(strict_types=1);

namespace Atk4\Data\Util;

class DeepCopyException extends \atk4\data\Exception
{
    public function addDepth(string $prefix)
    {
        $this->addMoreInfo('depth', $prefix . ':' . $this->getParams()['depth']);

        return $this;
    }
}
