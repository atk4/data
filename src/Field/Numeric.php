<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;

class Numeric extends Field
{
    /**
     * Specify how many decimal numbers should be saved. Set this to 0 for integers.
     */
    public $decimalNumbers = 8;

    /**
     * Set this to `true` if you wish to also store negative values.
     */
    public $signed = true;

    /**
     * @var mixed specify a minimum value for this number.
     */
    public $min;

    /**
     * @var mixed specify a maximum value for this number.
     */
    public $max;
}
