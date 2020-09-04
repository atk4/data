<?php

declare(strict_types=1);

namespace atk4\data\Exception;

class DeepCopyFailed extends \atk4\data\Exception
{
    public function addDepth(string $prefix)
    {
        $this->addMoreInfo('depth', $prefix . ':' . $this->getParams()['depth']);

        return $this;
    }
}
