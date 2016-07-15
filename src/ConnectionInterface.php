<?php

namespace atk4\data;

interface ConnectionInterface
{
    /**
     * Create new instance of the model.
     */
    public function add($model_name);
}
