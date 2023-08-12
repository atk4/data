<?php

declare(strict_types=1);

namespace Atk4\Data;

class ValidationException extends Exception
{
    /** @var array<string, string> */
    public array $errors;

    /**
     * @param array<string, string> $errors
     *
     * @return \Exception
     */
    public function __construct(array $errors, Model $model = null)
    {
        if (count($errors) === 0) {
            throw new Exception('At least one error must be given');
        }

        $this->errors = $errors;

        if (count($errors) === 1) {
            parent::__construct(reset($errors));

            $this->addMoreInfo('field', array_key_first($errors));
        } else {
            parent::__construct('Multiple validation errors');

            $this->addMoreInfo('errors', $errors);
        }

        $this->addMoreInfo('model', $model);
    }
}
