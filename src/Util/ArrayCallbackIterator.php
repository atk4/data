<?php

declare(strict_types=1);

namespace atk4\data\Util;

class ArrayCallbackIterator extends \IteratorIterator
{
    private $fx;

    public function __construct(\Traversable $iterator, $fx)
    {
        parent::__construct($iterator);
        $this->fx = $fx;
    }

    public function current()
    {
        return ($this->fx)(parent::current(), $this->key());
    }
}
