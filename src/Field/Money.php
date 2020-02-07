<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

/**
 * Class Money offers a lightweight implementation of currencies.
 */
class Money extends Numeric
{
    /** @var string Field type for backward compatibility. */
    public $type = 'money';

    /**
     * @var int Specify how many decimal numbers should be saved.
     */
    public $decimals = 2;
}
