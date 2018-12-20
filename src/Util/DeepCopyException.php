<?php

namespace atk4\data\Util;

class DeepCopyException extends \atk4\data\Exception
{
    public function addDepth($prefix)
    {
        $this->addMoreInfo('depth', $prefix.':'.$this->getParams()['depth']);

        return $this;
    }
}
