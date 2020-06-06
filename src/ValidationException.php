<?php

namespace atk4\data;

/**
 * Class description?
 */
class ValidationException extends Exception
{
    /** @var array Array of errors */
    public $errors = [];

    /**
     * Constructor.
     *
     * @param array $errors Array of errors
     * @param mixed $intent
     *
     * @return \Exception
     */
    public function __construct($errors, $model = null, $intent = null)
    {
        $this->errors = $errors;

        $c = is_array($errors) ? count($errors) : 0;
        if ($c > 1) {
            return parent::__construct('Multiple unhandled validation errors')
                ->addMoreInfo('errors', $errors)
                ->addMoreInfo('intent', $intent)
                ->addMoreInfo('model', $model);
        }

        if ($c === 1) {
            // foreach here just to get key/value from a single member
            foreach ($errors as $field => $error) {
                return parent::__construct($error)
                    ->addMoreInfo('field', $field)
                    ->addMoreInfo('model', $model);
            }
        }

        return parent::__construct('Incorrect use of ValidationException, argument should be an array')
            ->addMoreInfo('errors', $errors)
            ->addMoreInfo('intent', $intent)
            ->addMoreInfo('model', $model);
    }
}
