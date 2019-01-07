<?php


namespace atk4\data\Field;


/**
 * Class Money offers a lightweight implementation of currencies. If you plan to do anything at all with the
 * money, you should consider atk4/money add-on.
 */
class Money extends Number
{
    public $decimalNumbers = 2;
}