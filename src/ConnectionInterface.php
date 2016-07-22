<?php

namespace atk4\data;

interface ConnectionInterface
{
    /**
     * Create new instance of the model.
     *
     * @param string $model_name Name of the model
     */
    public function add($model_name);
}
