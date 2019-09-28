<?php

namespace atk4\data;

class Locale
{
    protected function __construct()
    {
        throw new Exception('Class Locale is needed only for locating the default translations');
    }

    public static function getPath()
    {
        return __DIR__.'/../locale/';
    }
}
