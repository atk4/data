<?php

namespace atk4\data\tests;

class AgileExceptionWrapper extends \PHPUnit_Framework_Exception
{
    public $previous;

    public function __construct($message = '', $code = 0, \Exception $previous = null)
    {
        $previous = $previous;
        parent::__construct($message, $code, $previous);
    }
}
