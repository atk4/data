<?php

declare(strict_types=1);

namespace atk4\data;

class Locale
{
    public function __construct()
    {
        throw new Exception('Class Locale is needed only for locating the default translations');
    }

    /**
     * Get absolute Path of default translations.
     */
    public static function getPath(): string
    {
        return dirname(__DIR__) . \DIRECTORY_SEPARATOR . 'locale';
    }
}
