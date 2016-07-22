<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Class description?
 */
interface ConnectionInterface
{
    /**
     * Create new instance of the model.
     *
     * @param string $model_name Name of the model
     */
    public function add($model_name);
}
