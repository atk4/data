<?php


namespace atk4\data\Field;


class Percent extends Number
{
    /**
     * @var int IMPORTANT: set to 2+precision, since percentage is stored as a 0 .. 1
     */
    public $decimalNumbers = 2;

    function format() {
        return ($this->value()*100).'%';
    }
}