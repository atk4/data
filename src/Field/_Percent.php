<?php
/**
 * Currently unused class - in development.
 */

namespace atk4\data\Field;

class _Percent extends Number
{
    /**
     * @var int IMPORTANT: set to 2+precision, since percentage is stored as a 0 .. 1
     */
    public $decimalNumbers = 2;

    public function format()
    {
        return ($this->value() * 100).'%';
    }
}
