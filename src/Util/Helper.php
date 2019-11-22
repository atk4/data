<?php

namespace atk4\data\Util;

use atk4\core\Exception;
//use atk4\data\Field;
//use atk4\data\Model;

/**
 * Helper class contains few useful methods which we share and use in other classes.
 */
class Helper
{
    use \atk4\core\DebugTrait;

    /**
     * Generates human readable caption from camelCase model class name or field names.
     *
     * This will translate 'this\\ _isNASA_MyBigBull shit_123\Foo'
     * into 'This Is NASA My Big Bull Shit 123 Foo'
     *
     * @param string $s
     *
     * @return string
     */
    static public function readableCaption($s)
    {
        //$s = 'this\\ _isNASA_MyBigBull shit_123\Foo';

        // first remove not allowed characters and uppercase words
        $s = ucwords(preg_replace('/[^a-z0-9]+/i', ' ', $s));

        // and then run regex to split camelcased words too
        $s = array_map('trim', preg_split('/^[^A-Z\d]+\K|[A-Z\d][^A-Z\d]+\K/', $s, -1, PREG_SPLIT_NO_EMPTY));
        $s = implode(' ', $s);

        return $s;
    }
}
