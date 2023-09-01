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
        if (count($data) > 0 && !is_array(reset($data))) {
            $dataOrig = $data;
            $data = [];
            foreach ($dataOrig as $k => $v) {
                $data[] = ['id' => $k, 'name' => $v];
            }
        }

        // detect types from values
        $fieldTypes = [];
        foreach ($data as $row) {
            foreach ($row as $k => $v) {
                if (isset($fieldTypes[$k])) {
                    continue;
                }

                if (is_bool($v)) {
                    $fieldType = 'boolean';
                } elseif (is_int($v)) {
                    $fieldType = 'integer';
                } elseif (is_float($v)) {
                    $fieldType = 'float';
                } elseif ($v instanceof \DateTimeInterface) {
                    $fieldType = 'datetime';
                } elseif (is_array($v)) {
                    $fieldType = 'json';
                } elseif (is_object($v)) {
                    $fieldType = 'object';
                } elseif ($v !== null) {
                    $fieldType = 'string';
                } else {
                    $fieldType = null;
                }

                $fieldTypes[$k] = $fieldType;
            }
        }
        foreach ($fieldTypes as $k => $fieldType) {
            if ($fieldType === null) {
                $fieldTypes[$k] = 'string';
            }
        }

        if (isset($fieldTypes['name'])) {
            $this->titleFieldForModel = 'name';
        } elseif (isset($fieldTypes['title'])) {
            $this->titleFieldForModel = 'title';
        }

        $defTypes = [];
        $keyOverride = [];
        $mustOverride = false;
        foreach ($fieldTypes as $k => $fieldType) {
            $defTypes[$k] = ['type' => $fieldType];

            // id information present, use it instead
            if ($k === 'id') {
                $mustOverride = true;
            }

            // if title is not set, use first key
            if (!$this->titleFieldForModel) {
                if (is_int($k)) {
                    $keyOverride[$k] = 'name';
                    $this->titleFieldForModel = 'name';
                    $mustOverride = true;

                    continue;
                }

                $this->titleFieldForModel = $k;
            }

            if (is_int($k)) {
                $keyOverride[$k] = 'field' . $k;
                $mustOverride = true;
            } else {
                $keyOverride[$k] = $k;
            }
        }

        if ($mustOverride) {
            $dataOrig = $data;
            $data = [];
            foreach ($dataOrig as $k => $row) {
                $row = array_combine($keyOverride, $row);
                if (isset($row['id'])) {
                    $k = $row['id'];
                }
                $data[$k] = $row;
            }
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
            \Closure::bind(static function () use ($model) {
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
