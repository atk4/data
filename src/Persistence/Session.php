<?php

namespace atk4\data\Persistence;

/**
 * Array persistence which will store model data in session.
 */
class Session extends Array_
{
    /**
     * Session container key.
     *
     * @var string
     */
    protected $session_key = '__atk_session';

    /**
     * Constructor. Can pass array of data in parameters.
     */
    public function __construct(&$data = [], string $key = null)
    {
        $key = $key ?? $this->name ?? static::class;

        parent::__construct($data);
        $_SESSION[$this->session_key][$key] = &$this->data;
    }
}
