<?php

declare(strict_types=1);

namespace atk4\data\Persistence\Csv;

use atk4\data\Model;
use atk4\data\Persistence;

/**
 * Class to perform queries on Csv persistence.
 */
class Query extends Persistence\IteratorQuery
{
    public function __construct(Model $model, Persistence $persistence = null)
    {
        parent::__construct($model, $persistence);

        $this->fx = function (\Iterator $iterator) {
            $keys = $this->fields ? array_flip((array) $this->fields) : [];

            $header = $this->persistence->getFileHeader();

            return new Persistence\ArrayCallbackIterator($iterator, function ($row, $id) use ($header, $keys) {
                if (!$row) {
                    return [];
                }

                $row = array_combine($header, $row);

                if ($this->model->id_field) {
                    $row[$this->model->id_field] = $id;
                }

                return $keys ? array_intersect_key($row, $keys) : $row;
            });
        };
    }

    protected function initSelect($fields = null): void
    {
        if ($fields) {
            $this->fields = $fields;
        }
    }
}
