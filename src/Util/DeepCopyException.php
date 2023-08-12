<?php

declare(strict_types=1);

namespace Atk4\Data\Util;

use Atk4\Data\Exception;

class DeepCopyException extends Exception
{
    /**
     * @return $this
     */
    public function addDepth(string $prefix)
    {
        $this->addMoreInfo('depth', $prefix . ':' . $this->getParams()['depth']);

        return $this;
    }
}
