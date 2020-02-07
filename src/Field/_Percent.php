<?php
/**
 * Currently unused class - in development.
 */

namespace atk4\data\Field;

class _Percent extends Numeric
{
    /**
     * @var int Specify how many decimal numbers should be saved.
     *          IMPORTANT: set to 2+precision, since percentage is stored as a 0 .. 1
     */
    public $decimals = 2;

    /*
    public function format()
    {
        return ($this->value() * 100).'%';
    }
    */
}
