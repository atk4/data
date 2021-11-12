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
    /**
     * This will be the title field for the model.
     *
     * @var string
     */
    public $titleForModel;

    /**
     * Populate the following fields for the model.
     *
     * @var array<string, array>
     */
    public $fieldsForModel = [];

    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array $data Static data in one of supported formats
     */
    public function __construct(array $data = null)
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

            $this->titleForModel = 'name';
            $this->fieldsForModel = [
                'id' => ['type' => $allKeysInt ? 'integer' : 'string'],
                'name' => ['type' => 'string'], // TODO type should be guessed as well
            ];

            parent::__construct($data);

            return;
        }

        if (isset($row1['name'])) {
            $this->titleForModel = 'name';
        } elseif (isset($row1['title'])) {
            $this->titleForModel = 'title';
        }

        $key_override = [];
        $def_types = [];
        $must_override = false;

        foreach ($row1 as $key => $value) {
            // id information present, use it instead
            if ($key === 'id') {
                $must_override = true;
            }

            // try to detect type of field by its value
            if (is_int($value)) {
                $def_types[] = ['type' => 'integer'];
            } elseif ($value instanceof \DateTime) {
                $def_types[] = ['type' => 'datetime'];
            } elseif (is_bool($value)) {
                $def_types[] = ['type' => 'boolean'];
            } elseif (is_float($value)) {
                $def_types[] = ['type' => 'float'];
            } elseif (is_array($value)) {
                $def_types[] = ['type' => 'json'];
            } elseif (is_object($value)) {
                $def_types[] = ['type' => 'object'];
            } else {
                $def_types[] = ['type' => 'string'];
            }

            // if title is not set, use first key
            if (!$this->titleForModel) {
                if (is_int($key)) {
                    $key_override[] = 'name';
                    $this->titleForModel = 'name';
                    $must_override = true;

                    continue;
                }

                $this->titleForModel = $key;
            }

            if (is_int($key)) {
                $key_override[] = 'field' . $key;
                $must_override = true;

                continue;
            }

            $key_override[] = $key;
        }

        if ($must_override) {
            $data2 = [];

            foreach ($data as $key => $row) {
                $row = array_combine($key_override, $row);
                if (isset($row['id'])) {
                    $key = $row['id'];
                }
                $data2[$key] = $row;
            }
            $data = $data2;
        }

        $this->fieldsForModel = array_combine($key_override, $def_types);
        parent::__construct($data);
    }

    public function add(Model $model, array $defaults = []): Model
    {
        if ($model->id_field && !$model->hasField($model->id_field)) {
            // init model, but prevent array persistence data seeding, id field with correct type must be setup first
            \Closure::bind(function () use ($model, $defaults) {
                $hadData = true;
                if (!isset($this->data[$model->table])) {
                    $hadData = false;
                    $this->data[$model->table] = true; // @phpstan-ignore-line
                }
                try {
                    parent::add($model, $defaults);
                } finally {
                    if (!$hadData) {
                        unset($this->data[$model->table]);
                    }
                }
            }, $this, Array_::class)();

            if (isset($this->fieldsForModel[$model->id_field])) {
                $model->getField($model->id_field)->type = $this->fieldsForModel[$model->id_field]['type'];
            }
        }
        $this->addMissingFieldsToModel($model);

        return parent::add($model, $defaults);
    }

    /**
     * Automatically adds missing model fields.
     */
    protected function addMissingFieldsToModel(Model $model): void
    {
        if ($this->titleForModel) {
            $model->title_field = $this->titleForModel;
        }

        foreach ($this->fieldsForModel as $field => $def) {
            if ($model->hasField($field)) {
                continue;
            }

            $model->addField($field, $def);
        }
    }
}
