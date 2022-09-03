<?php

declare(strict_types=1);

namespace Atk4\Data\Persistence;

use Atk4\Data\Model;

/**
 * Implements a very basic array-access pattern:.
 *
 * $m = new Model(Persistence\Static_(['hello', 'world']));
 * $m->load(1);
 *
 * echo $m->get('name'); // world
 */
class Static_ extends Array_
{
    /** @var string This will be the title field for the model. */
    public $titleFieldForModel;

    /** @var array<string, array<mixed>> Populate the following fields for the model. */
    public $fieldsForModel = [];

    /**
     * @param array<int|string, mixed> $data
     */
    public function __construct(array $data = [])
    {
        // chomp off first row, we will use it to deduct fields
        $row1 = reset($data);

        if (!is_array($row1)) {
            // convert array of strings into array of hashes
            $allKeysInt = true;
            foreach ($data as $k => $str) {
                $data[$k] = ['name' => $str];

                if (!is_int($k)) {
                    $allKeysInt = false;
                }
            }
            unset($str);

            $this->titleFieldForModel = 'name';
            $this->fieldsForModel = [
                'id' => ['type' => $allKeysInt ? 'integer' : 'string'],
                'name' => ['type' => 'string'], // TODO type should be guessed as well
            ];

            parent::__construct($data);

            return;
        }

        if (isset($row1['name'])) {
            $this->titleFieldForModel = 'name';
        } elseif (isset($row1['title'])) {
            $this->titleFieldForModel = 'title';
        }

        $keyOverride = [];
        $defTypes = [];
        $mustOverride = false;

        foreach ($row1 as $key => $value) {
            // id information present, use it instead
            if ($key === 'id') {
                $mustOverride = true;
            }

            // try to detect type of field by its value
            if (is_bool($value)) {
                $defTypes[] = ['type' => 'boolean'];
            } elseif (is_int($value)) {
                $defTypes[] = ['type' => 'integer'];
            } elseif (is_float($value)) {
                $defTypes[] = ['type' => 'float'];
            } elseif ($value instanceof \DateTimeInterface) {
                $defTypes[] = ['type' => 'datetime'];
            } elseif (is_array($value)) {
                $defTypes[] = ['type' => 'json'];
            } elseif (is_object($value)) {
                $defTypes[] = ['type' => 'object'];
            } else {
                $defTypes[] = ['type' => 'string'];
            }

            // if title is not set, use first key
            if (!$this->titleFieldForModel) {
                if (is_int($key)) {
                    $keyOverride[] = 'name';
                    $this->titleFieldForModel = 'name';
                    $mustOverride = true;

                    continue;
                }

                $this->titleFieldForModel = $key;
            }

            if (is_int($key)) {
                $keyOverride[] = 'field' . $key;
                $mustOverride = true;

                continue;
            }

            $keyOverride[] = $key;
        }

        if ($mustOverride) {
            $data2 = [];

            foreach ($data as $key => $row) {
                $row = array_combine($keyOverride, $row);
                if (isset($row['id'])) {
                    $key = $row['id'];
                }
                $data2[$key] = $row;
            }
            $data = $data2;
        }

        $this->fieldsForModel = array_combine($keyOverride, $defTypes);

        parent::__construct($data);
    }

    public function add(Model $model, array $defaults = []): void
    {
        if ($model->idField && !$model->hasField($model->idField)) {
            // init model, but prevent array persistence data seeding, id field with correct type must be setup first
            \Closure::bind(function () use ($model, $defaults) {
                $hadData = true;
                if (!isset($this->data[$model->table])) {
                    $hadData = false;
                    $this->data[$model->table] = true;
                }
                try {
                    parent::add($model, $defaults);
                } finally {
                    if (!$hadData) {
                        unset($this->data[$model->table]);
                    }
                }
            }, $this, Array_::class)();
            \Closure::bind(function () use ($model) {
                $model->_persistence = null;
            }, null, Model::class)();

            if (isset($this->fieldsForModel[$model->idField])) {
                $model->getField($model->idField)->type = $this->fieldsForModel[$model->idField]['type'];
            }
        }
        $this->addMissingFieldsToModel($model);

        parent::add($model, $defaults);
    }

    /**
     * Automatically adds missing model fields.
     */
    protected function addMissingFieldsToModel(Model $model): void
    {
        if ($this->titleFieldForModel) {
            $model->titleField = $this->titleFieldForModel;
        }

        foreach ($this->fieldsForModel as $field => $def) {
            if ($model->hasField($field)) {
                continue;
            }

            $model->addField($field, $def);
        }
    }
}
