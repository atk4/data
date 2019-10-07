<?php

namespace atk4\data\tests;

use atk4\data\Exception;
use atk4\data\Locale;

class LocaleTest extends \PHPUnit_Framework_TestCase
{

    public function testException() {
        $this->expectException(Exception::class);
        $exc = new Locale();
    }

    public function testGetPath()
    {
        $this->assertEquals(dirname(__DIR__).'/src/../locale/',Locale::getPath());
    }
}
