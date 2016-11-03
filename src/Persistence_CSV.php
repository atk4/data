<?php

// vim:ts=4:sw=4:et:fdm=marker:fdl=0

namespace atk4\data;

/**
 * Implements persistence driver that can save data and load from CSV file.
 * This basic driver only offers the load/save does not offer conditions or
 * id-specific operations.
 */
class Persistence_CSV extends Persistence
{
    /**
     * Name of the file.
     *
     * @var string
     */
    public $file;

    /**
     * Filehandle, when the $file is opened.
     *
     * @var resource
     */
    public $handle = null;

    /**
     * Constructor. Can pass array of data in parameters.
     *
     * @param array &$data
     */
    public function __construct($file)
    {
        $this->file = $file;
    }

    /**
     * Associate model with the data driver.
     *
     * @param Model|string $m        Model which will use this persistence
     * @param array        $defaults Properties
     *
     * @return Model
     */
    public function add($m, $defaults = [])
    {
        if (isset($defaults[0])) {
            $m->file = $defaults[0];
            unset($defaults[0]);
        }

        return parent::add($m, $defaults);
    }

    /**
     * Loads model and returns data record.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     *
     * @return array
     */
    public function load(Model $m, $id, $table = null)
    {
        if (!isset($this->data[$m->table]) && !isset($table)) {
            throw Exception([
                'Table was not found in the array data source',
                'table' => $m->table,
            ]);
        }
        if (!isset($this->data[$table ?: $m->table][$id])) {
            throw new Exception([
                'Record with specified ID was not found',
                'id' => $id,
            ], 404);
        }

        return $this->tryLoad($m, $id, $table);
    }

    /**
     * When load opeartion starts, this will open file and read
     * the first line. This line is then used to identify
     * columns.
     */
    public function loadHeader()
    {
        // Overide this method and open handle yourself if you want to
        // reposition or load some extra columns on the top.
        if (!$this->handle) {
            $this->handle = fopen($this->file, 'r');
        }

        $header = fgetcsv($this->handle);

        $this->initializeHeader($header);
    }

    /**
     * Remembers $this->header so that the data can be
     * easier mapped.
     */
    public function initializeHeader($header)
    {
        $this->header = $header;
        $this->header_reverse = [];

        // "ass" is short for associative
        foreach ($header as $num => $ass) {
            $this->header_reverse[$ass] = $num;
        }
    }

    public function typecastLoadRow(Model $m, $row)
    {
        $row = array_combine($this->header, $row);

        foreach ($row as $key => &$value) {
            if ($value === null) {
                continue;
            }

            if ($f = $m->hasElement($key)) {
                $value = $this->typecastLoadField($f, $value);
            }
        }

        return $row;
    }

    /**
     * Tries to load model and return data record.
     * Doesn't throw exception if model can't be loaded.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     *
     * @return array
     */
    public function loadAny(Model $m)
    {
        if (!$this->handle) {
            $this->loadHeader();
        }

        return $this->typecastLoadRow($m, fgetcsv($this->handle));
    }

    /**
     * Inserts record in data array and returns new record ID.
     *
     * @param Model  $m
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function insert(Model $m, $data, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $id = $this->generateNewID($m, $table);
        $data[$m->id_field] = $id;
        $this->data[$table][$id] = $data;

        return $id;
    }

    /**
     * Updates record in data array and returns record ID.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param array  $data
     * @param string $table
     *
     * @return mixed
     */
    public function update(Model $m, $id, $data, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $this->data[$table][$id] =
            array_merge(
                isset($this->data[$table][$id]) ? $this->data[$table][$id] : [],
                $data
            );

        return $id;
    }

    /**
     * Deletes record in data array.
     *
     * @param Model  $m
     * @param mixed  $id
     * @param string $table
     */
    public function delete(Model $m, $id, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        unset($this->data[$table][$id]);
    }

    /**
     * Generates new record ID.
     *
     * @param Model  $m
     * @param string $table
     *
     * @return string
     */
    public function generateNewID($m, $table = null)
    {
        if (!isset($table)) {
            $table = $m->table;
        }

        $ids = array_keys($this->data[$table]);

        $type = $m->getElement($m->id_field)->type;

        switch ($type) {
            case 'integer':
                return count($ids) === 0 ? 1 : (max($ids) + 1);
            case 'string':
                return uniqid();
            default:
                throw new Exception([
                    'Unsupported id field type. Array supports type=integer or type=string only',
                    'type' => $type,
                ]);
        }
    }
}
