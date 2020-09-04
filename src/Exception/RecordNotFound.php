<?php

declare(strict_types=1);

namespace atk4\data\Exception;

use atk4\data\Model;

class RecordNotFound extends \atk4\data\Exception
{
    public function __construct(string $message = '', int $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message ?: 'Record not found', $code ?: 404, $previous);
    }

    public function setRecordParameters(Model $model, $id = null)
    {
        $this
            ->addMoreInfo('model', $model)
            ->addMoreInfo('scope', $model->scope()->toWords());

        if ($id !== null) {
            $this->addMoreInfo('id', $id);
        }

        return $this;
    }
}
