<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

class ValidationException extends Exception
{
    public $errors = [];

    public function __construct($errors, $intent = null)
    {
        $this->errors = $errors;

        $c = count($errors);
        if ($c > 1) {
            return parent::__construct([
                'Multiple unhandled validation errors',
                'errors'=> $errors,
                'intent'=> $intent,
            ]);
        }
        if ($c === 1) {

            // foreach here just to get key/value from a single member
            foreach ($errors as $field=>$error) {
                return parent::__construct([
                    $error,
                    'field'=> $field,
                ]);
            }
        }

        return parent::__construct([
            'Incorrect use of ValidationException, argument should be an array',
            'errors'=> $errors,
            'intent'=> $intent,
        ]);
    }
}
