<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;


use atk4\data\Field;

class Number extends Field
{
    /**
     * Specify how many decimal numbers should be saved. Set this to 0 for integers.
     */
    public $decimalNumbers = 8;

    /**
     * Set this to `true` if you wish to store negative or positive numbers.
     */
    public $signed = true;

    /**
     * @var string will put prefix before the number
     */
    public $prefix;

    /**
     * @var string will add postfix after the number when formatting
     */
    public $postfix;

    /**
     * @var mixed specify a minimum value for this number
     */
    public $min;

    /**
     * @var mixed specify a maximum value for this number
     */
    public $max;

    /**
     * @var int specify number base. 16 for hex, 2 for binary etc.
     */
    public $base = 10;

    /**
     * Format current field into user-friendly format
     */
    public function format()
    {

    }

}