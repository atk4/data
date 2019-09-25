<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data\Field;

use atk4\data\Field;

/**
 * Class Money offers a lightweight implementation of currencies.
 */
class Money extends Numeric
{
    /** @var string Field type for backward compatibility. */
    public $type = 'money';

    /**
     * @var int Specify how many decimal numbers should be saved.
     */
    public $decimal_numbers = 2;

    /**
     * Return array of seed properties of this Field object.
     *
     * @param array $properties Properties to return, by default will return all properties set.
     *
     * @return array
     */
    public function getSeed(array $properties = []) : array
    {
        $seed = parent::getSeed($properties);

        // [key => default_value]
        $properties = $properties ?: [
            'decimal_numbers' => 2,
        ];

        foreach ($properties as $k=>$v) {
            if ($this->{$k} !== $v) {
                $seed[$k] = $this->{$k};
            }
        }

        return $seed;
    }
}
